<?php

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/config.php';

use NewsdataIO\Exception\NewsdataException;
use NewsdataIO\NewsdataApi;

$newsdataApiObj = new NewsdataApi(NEWSDATA_API_KEY);

// A value may be a string or an array of strings (sent comma-separated).
$data = [
    'q'        => 'ronaldo',
    'country'  => ['ie', 'gb'],
    'language' => 'en',
];

try {
    $response = $newsdataApiObj->get_latest_news($data);
    var_dump($response);
} catch (NewsdataException $e) {
    echo 'Request failed: ' . $e->getMessage() . PHP_EOL;
}
