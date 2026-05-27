<?php

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/config.php';

use NewsdataIO\NewsdataApi;
use NewsdataIO\Exception\NewsdataException;

$newsdataApiObj = new NewsdataApi(NEWSDATA_API_KEY);

// from_date and to_date are required for every count endpoint.
$base = [
    'from_date' => '2024-01-01',
    'to_date'   => '2024-01-31',
    'interval'  => 'day',
];

try {
    $news   = $newsdataApiObj->get_news_count($base + ['q' => 'election']);
    $crypto = $newsdataApiObj->get_crypto_count($base + ['coin' => 'btc']);
    $market = $newsdataApiObj->get_market_count($base + ['symbol' => 'AAPL']);

    var_dump($news, $crypto, $market);
} catch (NewsdataException $e) {
    echo 'Request failed: ' . $e->getMessage() . PHP_EOL;
}
