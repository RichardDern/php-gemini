<?php

namespace RichardDern\Gemini\Traits;

use Monolog\Handler\HandlerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

trait Logs
{
    /**
     * Instance of Monolog.
     *
     * @var Monolog\Logger
     */
    protected $logger;

    /**
     * Log handler.
     *
     * @var Monolog\Handler\HandlerInterface
     */
    protected $logHandler;

    /**
     * Log level.
     *
     * @var int
     */
    protected $logLevel = Logger::INFO;

    /**
     * Log channel.
     *
     * @var string
     */
    protected $logChannel = 'php-gemini';

    /**
     * Chainable method to register a log handler.
     *
     * @return self
     */
    public function setLogHandler(HandlerInterface $handler)
    {
        $this->logHandler = $handler;

        $this->prepareLogger();

        return $this;
    }

    /**
     * Chainable method to define log level.
     *
     * @param mixed $level
     *
     * @return self
     */
    public function setLogLevel($level)
    {
        $this->logLevel = $level;

        $this->prepareLogger();

        return $this;
    }

    /**
     * Chainable method to define log channel.
     *
     * @param string $channel
     *
     * @return self
     */
    public function setLogChannel($channel)
    {
        $this->logChannel = $logChannel;

        $this->prepareLogger();

        return $this;
    }

    /**
     * Prepare Monolog.
     *
     * @param mixed $channel
     */
    protected function prepareLogger()
    {
        if (empty($this->logHandler)) {
            $this->logHandler = new StreamHandler('./'.$this->logChannel.'.log', $this->logLevel);
        }

        $this->logger = new Logger($this->logChannel);
        $this->logger->pushHandler($this->logHandler);
    }
}
