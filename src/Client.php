<?php

declare(strict_types = 1);

namespace RichardDern\Gemini;

use League\Uri\Uri;
use React\EventLoop\Factory as LoopFactory;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use RichardDern\Gemini\Constants\ResponseStatusCodes;
use RichardDern\Gemini\Exceptions\InvalidMetaException;
use RichardDern\Gemini\Exceptions\InvalidStatusException;
use RichardDern\Gemini\Traits\HandlesUri;
use RichardDern\Gemini\Traits\Logs;

/**
 * Interacts with a Gemini server.
 */
class Client
{
    use HandlesUri;
    use Logs;

    // -------------------------------------------------------------------------
    // ----[ Properties ]-------------------------------------------------------
    // -------------------------------------------------------------------------

    /**
     * Hostname or IP address of target server.
     *
     * @var string
     */
    protected $server;

    /**
     * Port the server listens to.
     *
     * @var int
     */
    protected $port = 1965;

    /**
     * Last URI requested from the server.
     *
     * @var string|Uri
     */
    protected $requestedUri;

    /**
     * Base URI used to resolve relative URIs.
     *
     * @var string|Uri
     */
    protected $baseUri;

    /**
     * Socket used to connect to the server.
     */
    protected $clientSocket;

    /**
     * Connection loop.
     *
     * @var \React\EventLoop\Factory
     */
    protected $loop;

    /**
     * Raw response as returned by the server.
     *
     * @var string
     */
    protected $rawResponse;

    /**
     * Built request to send to the server.
     *
     * @var string
     */
    protected $request;

    /**
     * Keep a history of redirections for the last request.
     *
     * @var array
     */
    protected $redirections = [];

    /**
     * Maximum number of redirections to follow.
     *
     * @var int
     */
    protected $maxRedirections = 10;

    // -------------------------------------------------------------------------
    // ----[ Methods ]----------------------------------------------------------
    // -------------------------------------------------------------------------

    /**
     * Creates a new client instance. Optionally specifies server and port to
     * target.
     *
     * @param null|string $server Hostname or IP address of target server
     * @param null|int    $port   Port the server listens to. Defaults to 1965
     */
    public function __construct($server = null, int $port = 1965)
    {
        $this->prepareLogger();

        $this->logger->debug('Booting client');

        $this->setServer($server);
        $this->setPort($port);
    }

    // ----[ Accessors ]--------------------------------------------------------

    /**
     * Return base URI (as a League\Uri\Uri object) used to resolve relative
     * URIs.
     *
     * @return League\Uri\Uri
     */
    public function getBaseUri()
    {
        return $this->baseUri;
    }

    /**
     * Return requested URI (as a League\Uri\Uri object).
     *
     * @return League\Uri\Uri
     */
    public function getRequestedUri()
    {
        return $this->requestedUri;
    }

    /**
     * Return redirections logged for the last request.
     *
     * @return array
     */
    public function getRedirections()
    {
        return $this->redirections;
    }

    // ----[ Mutators ]---------------------------------------------------------

    /**
     * Chainable method to set target server hostname or IP address.
     *
     * @param string $server Hostname or IP address of target server
     *
     * @return self
     */
    public function setServer($server)
    {
        $this->server = $server;

        $this->logger->debug(sprintf('Server set: %s', $this->server));

        return $this;
    }

    /**
     * Chainable method to set the port target server listens to. Calling this
     * method is not needed, unless target server does not listen on default
     * port (1965).
     *
     * @param int $port
     *
     * @return self
     */
    public function setPort($port)
    {
        $this->port = $port;

        $this->logger->debug(sprintf('Port set: %d', $this->port));

        return $this;
    }

    /**
     * Chainable method to set base URI, which will be used to resolve any
     * relative URIs you might want to query.
     *
     * @param \League\Uri\Uri|string $uri
     *
     * @return self
     */
    public function setBaseUri($uri)
    {
        $uri = $this->parseUri($uri);

        $this->validateUri($uri);

        $this->baseUri = $uri;

        $this->logger->debug(sprintf('Base URI set: %s', (string) $this->baseUri));

        return $this;
    }

    /**
     * Requests specified URI. Return server's response.
     *
     * @param \League\Uri\Uri|string $uri
     */
    public function request($uri)
    {
        if (!empty($this->server)) {
            $this->setBaseUri(Uri::createFromString(sprintf('gemini://%s:%d', $this->server, $this->port)));
        }

        $this->setRequestedUri($uri);

        $this->request = sprintf("%s\r\n", (string) $this->requestedUri);
        $target        = (string) $this->requestedUri->withScheme('tls');

        $this->rawResponse = '';

        $this->logger->debug('Connecting to server...');

        $this->createLoop();

        $connector = $this->createConnector();

        $connector->connect($target)->then(function (ConnectionInterface $connection) {
            $this->logger->debug('Connected to server');

            $connection->on('data', function ($chunk) {
                $this->logger->debug('Chunk received', [$chunk]);

                $this->rawResponse .= $chunk;
            });

            $connection->on('end', function () {
                $this->logger->debug('Transmission ended');
            });

            $connection->on('error', function (Exception $e) {
                $this->logger->error($e->getMessage());
            });

            $connection->on('close', function () {
                $this->logger->debug('Connection closed');
            });

            $this->logger->debug(sprintf('Sending request: %s', $this->request));

            $connection->write($this->request);
        });

        $this->loop->run();

        return $this->parseResponse();
    }

    // -------------------------------------------------------------------------

    /**
     * Create the connection loop.
     */
    protected function createLoop()
    {
        $this->logger->debug('Creating request loop...');
        $this->loop = LoopFactory::create();
        $this->logger->debug('Created request loop');
    }

    /**
     * Create a connector with appropriate settings.
     */
    protected function createConnector()
    {
        return new Connector($this->loop, [
            'tls' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ]);
    }

    /**
     * Chainable method to set requested URI.
     *
     * @param \League\Uri\Uri|string $uri
     *
     * @return self
     */
    protected function setRequestedUri($uri)
    {
        $uri = $this->parseUri($uri, true);

        $this->validateUri($uri);

        $this->requestedUri = $uri;

        $this->logger->debug(sprintf('Requested URI set: %s', (string) $this->requestedUri));

        return $this;
    }

    /**
     * Parse a raw response to produce a formated Response object.
     *
     * @param mixed $rawResponse
     *
     * @throws RichardDern\Gemini\Exceptions\InvalidStatusException
     * @throws RichardDern\Gemini\Exceptions\InvalidMetaException
     *
     * @return RichardDern\Gemini\Response
     */
    protected function parseResponse()
    {
        $this->logger->debug('Parsing response', [$this->rawResponse]);

        $matches = [];

        preg_match('/(?<status>\d{2})\s(?<meta>.*)\r\n(?<body>.*)/su', $this->rawResponse, $matches);

        $status = null;
        $meta   = null;
        $body   = null;

        if (array_key_exists('status', $matches)) {
            $status = trim($matches['status']);

            if (!\is_numeric($status)) {
                throw InvalidStatusException();
            }

            $status = (int) $status;
        }

        if (array_key_exists('meta', $matches)) {
            $meta = trim($matches['meta']);

            if (mb_strlen($meta) > 1024) {
                throw InvalidMetaException();
            }
        }

        if (array_key_exists('body', $matches)) {
            $body = trim($matches['body']);
        }

        if ($status === ResponseStatusCodes::TEMPORARY_REDIRECT || $status === ResponseStatusCodes::PERMANENT_REDIRECT) {
            return $this->handleRedirection($status, $meta);
        }

        $response = new Response($status, $meta, $body);

        $this->logger->debug('Response parsed', [$response]);

        return $response;
    }

    /**
     * Handle a redirection.
     *
     * @param int    $status Response status code
     * @param string $meta   Response meta
     */
    protected function handleRedirection(int $status, string $meta)
    {
        if (count($this->redirections) === $this->maxRedirections) {
            $this->logger->error('Maximum redirections reached');

            throw new TooManyRedirectionsException();
        }

        $permanent   = $status === ResponseStatusCodes::PERMANENT_REDIRECT;
        $redirectUri = $this->parseUri($meta);

        $this->logger->debug('Redirection', ['Uri' => (string) $redirectUri, 'Permanent' => $permanent]);

        $this->redirections[] = [
            'uri'       => $redirectUri,
            'permanent' => $permanent,
        ];

        return $this->request($redirectUri);
    }
}
