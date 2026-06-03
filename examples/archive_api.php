<?php

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/config.php';

use NewsdataIO\Exception\NewsdataException;
use NewsdataIO\NewsdataApi;

$newsdataApiObj = new NewsdataApi(NEWSDATA_API_KEY);

$data = [
    'q'         => 'ronaldo',
    'from_date' => '2024-01-01',
    'to_date'   => '2024-01-31',
];

try {
    $response = $newsdataApiObj->news_archive($data);
    var_dump($response);
} catch (NewsdataException $e) {
    echo 'Request failed: ' . $e->getMessage() . PHP_EOL;
}
