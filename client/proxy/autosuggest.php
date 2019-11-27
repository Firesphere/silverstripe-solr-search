<?php

use GuzzleHttp\Client;

if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php')) {
    require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
}

$request = filter_input(INPUT_GET, 'query', FILTER_SANITIZE_STRING);

$client = new Client();
$suggestions = $client->request('GET', 'http://localhost:8983/solr/My-Solr-Core/suggest?q=' . $request);
header('Content-Type: application/json');
echo($suggestions->getBody());
