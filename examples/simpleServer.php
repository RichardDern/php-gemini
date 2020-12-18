<?php

require_once '../vendor/autoload.php';

$server = new RichardDern\Gemini\Server('0.0.0.0', 1965);
$server->start();
