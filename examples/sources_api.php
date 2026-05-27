<?php

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/config.php';

use NewsdataIO\NewsdataApi;
use NewsdataIO\Exception\NewsdataException;

$newsdataApiObj = new NewsdataApi(NEWSDATA_API_KEY);

$data = [
    'country'  => 'us',
    'language' => 'en',
    'category' => 'business',
];

try {
    $response = $newsdataApiObj->news_sources($data);
    var_dump($response);
} catch (NewsdataException $e) {
    echo 'Request failed: ' . $e->getMessage() . PHP_EOL;
}
