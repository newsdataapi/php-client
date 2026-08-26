<?php

/**
 * Real-time news streaming.
 *
 *   NEWSDATA_API_KEY=<your key> php examples/websocket.php
 *
 * Streaming needs the optional phrity/websocket package (PHP 8.1+):
 *
 *   composer require phrity/websocket
 *
 * Articles are matched by a registered query. If NEWSDATA_REGISTRATION_ID is
 * set, that query is streamed directly; otherwise this registers a demo query
 * (q="pizza") first and prints the resulting registration_id so you can reuse
 * it on the next run — or remove it later with $ws->delete($id).
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use NewsdataIO\Exception\NewsdataAPIError;
use NewsdataIO\Exception\NewsdataWebSocketAuthError;
use NewsdataIO\Exception\NewsdataWebSocketError;
use NewsdataIO\NewsdataApi;
use NewsdataIO\NewsdataWebSocket;

$apiKey = getenv('NEWSDATA_API_KEY');
if ($apiKey === false || $apiKey === '') {
    fwrite(STDERR, "Set NEWSDATA_API_KEY in your environment before running this example.\n");
    exit(1);
}

$api = new NewsdataApi($apiKey);
$ws = new NewsdataWebSocket($api);

/**
 * Register q="pizza" and return its registration_id. Registering an identical
 * query again answers HTTP 409 with the existing id in the response body —
 * reuse it instead of failing.
 */
function registerDemoQuery(NewsdataWebSocket $ws): string
{
    try {
        $response = $ws->register(['q' => 'pizza']);
        $id = $response->results->registration_id;
        echo "registered demo query q=\"pizza\" -> {$id}\n";
        return $id;
    } catch (NewsdataAPIError $e) {
        // getResponseBody() decodes to an array regardless of the client's
        // setDecodeJsonAsArray() setting.
        $body = $e->getResponseBody();
        $existing = $body['results']['registration_id'] ?? null;
        if ($e->getStatusCode() === 409 && $existing !== null) {
            echo "query already registered; reusing {$existing}\n";
            return $existing;
        }
        throw $e;
    }
}

$registrationId = getenv('NEWSDATA_REGISTRATION_ID') ?: registerDemoQuery($ws);

echo "streaming {$registrationId} — Ctrl-C to stop\n";

try {
    foreach ($ws->stream($registrationId) as $response) {
        foreach ($response->results as $article) {
            echo $article->title, ' - ', $article->link, PHP_EOL;
        }
    }
} catch (NewsdataWebSocketAuthError $e) {
    fwrite(STDERR, 'rejected: ' . $e->getMessage() . PHP_EOL);
    exit(1);
} catch (NewsdataWebSocketError $e) {
    fwrite(STDERR, 'stream error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
