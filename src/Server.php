<?php

declare(strict_types = 1);

namespace RichardDern\Gemini;

use Monolog\Logger;
use React\EventLoop\Factory as LoopFactory;
use React\Socket\ConnectionInterface;
use React\Socket\SecureServer;
use React\Socket\TcpServer;
use RichardDern\Gemini\Constants\ResponseStatusCodes;
use RichardDern\Gemini\Traits\HandlesUri;
use RichardDern\Gemini\Traits\Logs;

/**
 * Runs a Gemini server.
 */
class Server
{
    use HandlesUri;
    use Logs;

    // -------------------------------------------------------------------------
    // ----[ Properties ]-------------------------------------------------------
    // -------------------------------------------------------------------------

    /**
     * Address the server will bind to. If null, server will bind to any
     * available interface.
     *
     * @var null|string
     */
    protected $address;

    /**
     * Port the server will listen to.
     *
     * @var int
     */
    protected $port;

    /**
     * Root directory of this server.
     *
     * @var string
     */
    protected $root;

    /**
     * Visitor connection.
     */
    protected $connection;

    /**
     * Full path to certificate.
     *
     * @var string
     */
    protected $certPath;

    // -------------------------------------------------------------------------
    // ----[ Methods ]----------------------------------------------------------
    // -------------------------------------------------------------------------

    /**
     * Initialize a new Server instance.
     *
     * @param null|string $address
     * @param array       $config  Server configuration
     */
    public function __construct($address = null, int $port = 1965, string $root = './www', string $certPath = '../localhost.pem')
    {
        $this->prepareLogger('Server', Logger::DEBUG);

        $this->address  = $address;
        $this->port     = $port;
        $this->root     = $root;
        $this->certPath = $certPath;
    }

    /**
     * Start the server.
     */
    public function start()
    {
        $this->createLoop();

        $server = $this->createSecureServer();

        $server->on('connection', function (ConnectionInterface $connection) {
            $this->connection = $connection;

            $this->connection->on('data', function ($data) {
                $this->logger->debug('Incoming request', [$data]);
                $this->handleInput($data);
            });

            $this->connection->on('end', function () {
                $this->logger->debug('Transmission ended');
            });

            $this->connection->on('error', function (Exception $e) {
                $this->logger->error($e->getMessage());
            });

            $this->connection->on('close', function () {
                $this->logger->debug('Connection closed');
            });
        });

        $this->loop->run();
    }

    /**
     * Stop the server.
     */
    public function stop()
    {
        $this->closeConnection(ResponseStatusCodes::SERVER_UNAVAILABLE, 'Server shutting down');
    }

    /**
     * Closes connection.
     *
     * @param mixed      $connection
     * @param mixed      $status
     * @param null|mixed $meta
     * @param null|mixed $body
     */
    public function closeConnection($status, $meta = null, $body = null)
    {
        $this->logger->debug('Sending response...', ['status' => $status, 'meta' => $meta, 'body' => $body]);

        $this->connection->write(sprintf("%d %s\r\n%s", $status, $meta, $body));

        $this->logger->debug('Sent response', ['status' => $status, 'meta' => $meta, 'body' => $body]);
        $this->logger->debug('Closing connection...');

        $this->connection->end();
    }

    /**
     * Handle a request.
     *
     * @param mixed $data
     * @param mixed $connection
     */
    protected function handleInput($data)
    {
        $matches = [];
        $uri     = false;

        preg_match('/(?<url>.*)\r\n/u', $data, $matches);

        if (array_key_exists('url', $matches)) {
            try {
                $uri = $this->parseUri($matches['url'], false);
            } catch (\Exception $ex) {
                $uri = false;
            }
        }

        if ($uri === false) {
            return $this->closeConnection(ResponseStatusCodes::PERMANENT_FAILURE, 'Invalid URL');
        }

        if (mb_strlen((string) $uri) > 1024) {
            return $this->closeConnection(ResponseStatusCodes::PERMANENT_FAILURE, 'URL too long');
        }

        $host = $uri->getHost();
        $path = $uri->getPath();
        $body = null;

        $pathsToTry = [
            sprintf('%s/%s/%s', $this->root, $host, $path),
            sprintf('%s/%s/%s', $this->root, 'default', $path),
        ];

        foreach ($pathsToTry as $filepath) {
            if (is_dir($filepath)) {
                if (\file_exists($filepath.'/index.gmi')) {
                    $body = \file_get_contents($filepath.'/index.gmi');

                    break;
                }
                if (\file_exists($filepath.'/index.gemini')) {
                    $body = \file_get_contents($filepath.'/index.gemini');

                    break;
                }
            }

            if (\file_exists($filepath)) {
                $body = \file_get_contents($filepath);

                break;
            }
        }

        if (empty($body)) {
            return $this->closeConnection(ResponseStatusCodes::NOT_FOUND, 'Not found');
        }

        return $this->closeConnection(ResponseStatusCodes::SUCCESS, 'text/gemini', $body);
    }

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
     * Create the TCP connector.
     */
    protected function createTcpServer()
    {
        $this->logger->debug('Creating TCP server...');

        $bind = '';

        if (!empty($this->address)) {
            $bind = $this->address.':';
        }

        $bind .= $this->port;

        $server = new TcpServer($bind, $this->loop);

        $this->logger->debug('Created TCP server', ['bind' => $bind]);

        return $server;
    }

    /**
     * Create a secure connector.
     */
    protected function createSecureServer()
    {
        $this->logger->debug('Creating Secure server...', [
            'local_cert' => $this->certPath,
        ]);

        $server = new SecureServer($this->createTcpServer(), $this->loop, [
            'local_cert' => $this->certPath,
        ]);

        $this->logger->debug('Created Secure server');

        return $server;
    }
}
