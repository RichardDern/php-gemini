<?php

namespace RichardDern\Gemini\Traits;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

trait Logs
{
    protected $logger;

    protected function prepareLogger($channel, $level = null)
    {
        if (empty($level)) {
            $level = Logger::INFO;
        }

        $this->logger = new Logger($channel);
        $this->logger->pushHandler(new StreamHandler('./'.$channel.'.log', $level));
    }
}
