<?php

declare(strict_types = 1);

namespace RichardDern\Gemini;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\StorageAttributes;
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
     * Visitor connection.
     */
    protected $connection;

    /**
     * Full path to certificate.
     *
     * @var string
     */
    protected $certPath;

    /**
     * Adapter used to interact with filesystem.
     *
     * @var League\Flysystem\FilesystemAdapter
     */
    protected $fileSystemAdapter;

    /**
     * File system to interact with.
     *
     * @var League\Flysystem\Filesystem
     */
    protected $fileSystem;

    /**
     * Boolean value indicating if we should auto-generate directory index.
     * Defaults to false.
     *
     * @var bool
     */
    protected $enableDirectoryIndex = false;

    // -------------------------------------------------------------------------

    /**
     * Initialize a new Server instance.
     *
     * @param null|string $address
     * @param null|int    $port
     */
    public function __construct($address = null, int $port = 1965, FilesystemAdapter $fileSystemAdapter = null, string $certPath = '../localhost.pem')
    {
        $this->prepareLogger('Server', Logger::DEBUG);

        $this->address           = $address;
        $this->port              = $port;
        $this->fileSystemAdapter = $fileSystemAdapter;
        $this->certPath          = $certPath;

        if (empty($this->fileSystemAdapter)) {
            $this->fileSystemAdapter = new LocalFilesystemAdapter('./www');
        }

        $this->fileSystem = new Filesystem($this->fileSystemAdapter);
    }

    // -------------------------------------------------------------------------
    // ----[ Methods ]----------------------------------------------------------
    // -------------------------------------------------------------------------

    // ----[ Mutators ]---------------------------------------------------------

    /**
     * Should we automatically generate directory index ? Chainable method.
     *
     * @return self
     */
    public function enableDirectoryIndex(bool $enable = true)
    {
        $this->logger->debug('Enable Directory Index', ['enable' => $enable]);

        $this->enableDirectoryIndex = $enable;

        return $this;
    }

    // -------------------------------------------------------------------------

    /**
     * Start the server.
     */
    public function start()
    {
        if (empty($this->certPath) || !\file_exists($this->certPath)) {
            throw new \Exception('No certificate available');
        }

        $this->logger->info('Starting server', ['address'=> $this->address, 'port' => $this->port]);

        $this->createLoop();

        $server = $this->createSecureServer();

        $this->logger->info('Waiting for connections');

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
        $this->logger->info('Stopping server');

        $this->closeConnection(ResponseStatusCodes::SERVER_UNAVAILABLE, 'Server is shutting down');
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
                $this->logger->error('Invalid URL', [$data]);

                return $this->closeConnection(ResponseStatusCodes::BAD_REQUEST, 'Invalid URL');
            }
        } else {
            $this->logger->error('Missing URL', [$data]);

            return $this->closeConnection(ResponseStatusCodes::BAD_REQUEST, 'URL is missing');
        }

        try {
            $this->validateUri($uri);
        } catch (\Exception $ex) {
            $this->logger->error('Invalid URL', [$data]);

            return $this->closeConnection(ResponseStatusCodes::BAD_REQUEST, $ex->getMessage());
        }

        $host = $uri->getHost();
        $path = $uri->getPath();

        $this->servePath($host, $path);
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

    /**
     * Send an appropriate response to the client, base on request's host and
     * path.
     *
     * @param mixed $host
     * @param mixed $path
     */
    protected function servePath($host, $path)
    {
        $this->logger->debug('Serving request', ['host' => $host, 'path' => $path]);

        $physicalPath = sprintf('%s/%s', $host, $path);

        if (!$this->fileSystem->fileExists($physicalPath)) {
            if ($this->enableDirectoryIndex) {
                return $this->directoryIndex($host, $path);
            }

            $this->logger->error('Not found', ['host' => $host, 'path' => $path]);

            return $this->closeConnection(ResponseStatusCodes::NOT_FOUND, 'Not found');
        }

        if (preg_match('/.*\.gemini$/', $path) || preg_match('/.*.\.gmi$/', $path)) {
            $mimeType = 'text/gemini';
        } else {
            $mimeType = $this->fileSystem->mimeType($physicalPath);
        }

        $body = $this->fileSystem->read($physicalPath);

        $this->closeConnection(ResponseStatusCodes::SUCCESS, $mimeType, $body);
    }

    /**
     * Build a directory index. If no directory and no files exists in specified
     * path, return a NOT_FOUND error. Otherwise, return a pre-built text/gemini
     * file listing directories and files within specified path and host.
     *
     * @param mixed $host
     * @param mixed $path
     */
    protected function directoryIndex($host, $path)
    {
        $this->logger->debug('Serving directory index', ['host' => $host, 'path' => $path]);

        $physicalPath = sprintf('%s/%s', $host, $path);

        $lines = [
            sprintf('# %s: %s', $host, $path),
        ];

        $directories = $this->fileSystem->listContents($physicalPath)
            ->filter(fn (StorageAttributes $attributes) => $attributes->isDir())
            ->map(fn (StorageAttributes $attributes)    => str_replace($host.'/', '', $attributes->path()))
            ->toArray();

        $files = $this->fileSystem->listContents($physicalPath)
            ->filter(fn (StorageAttributes $attributes) => $attributes->isFile())
            ->map(fn (StorageAttributes $attributes)    => str_replace($host.'/', '', $attributes->path()))
            ->toArray();

        if (count($directories) === 0 && count($files) === 0) {
            return $this->closeConnection(ResponseStatusCodes::NOT_FOUND, 'Not found');
        }

        foreach ($directories as $path) {
            $lines[] = sprintf('=> %s %s', $path, basename($path));
        }

        foreach ($files as $path) {
            $lines[] = sprintf('=> %s %s', $path, basename($path));
        }

        $this->closeConnection(ResponseStatusCodes::SUCCESS, 'text/gemini', implode("\n", $lines));
    }
}
