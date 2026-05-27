<?php

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/config.php';

use NewsdataIO\NewsdataApi;
use NewsdataIO\Exception\NewsdataException;

$newsdataApiObj = new NewsdataApi(NEWSDATA_API_KEY);

$data = [
    'q'    => 'bitcoin',
    'coin' => 'btc',
];

try {
    $response = $newsdataApiObj->get_crypto_news($data);
    var_dump($response);
} catch (NewsdataException $e) {
    echo 'Request failed: ' . $e->getMessage() . PHP_EOL;
}
