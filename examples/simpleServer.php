<?php

/**
 * Don't forget to generate a certificate before running this example.
 *
 * php ./bin/generate-self-signed-certificate.php > localhost.pem
 */
require_once '../vendor/autoload.php';

$server = new RichardDern\Gemini\Server('0.0.0.0', 1965);
$server->enableDirectoryIndex()
    ->start();
