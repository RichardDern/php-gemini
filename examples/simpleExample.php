<?php

require_once '../vendor/autoload.php';

$client   = new RichardDern\Gemini\Client('gemini.circumlunar.space');
$response = $client->request('/software/');

print_r($response);
