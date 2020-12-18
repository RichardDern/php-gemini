<?php

require_once '../vendor/autoload.php';

$client   = new RichardDern\Gemini\Client('127.0.0.1');
$response = $client->request('/software/test.gemini');

print_r($response);
