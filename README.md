<div align="center">

![Newsdata.io logo](https://raw.githubusercontent.com/newsdataapi/php-client/main/newsdata-logo.png)

# Newsdata.io PHP Client

[![Packagist Version](https://img.shields.io/packagist/v/newsdataio/newsdataapi?logo=packagist&color=f28d1a)](https://packagist.org/packages/newsdataio/newsdataapi)
[![CI](https://img.shields.io/github/actions/workflow/status/newsdataapi/php-client/ci.yml?branch=main&logo=github&label=CI)](https://github.com/newsdataapi/php-client/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/newsdataapi/php-client/branch/main/graph/badge.svg)](https://codecov.io/gh/newsdataapi/php-client)
[![PHP](https://img.shields.io/badge/php-%5E7.3%20%7C%7C%20%5E8.0-green?logo=php)](https://github.com/newsdataapi/php-client/blob/main/LICENSE)
[![License](https://img.shields.io/badge/license-MIT-blue)](https://github.com/newsdataapi/php-client/blob/main/LICENSE)
[![OpenAPI](https://img.shields.io/badge/OpenAPI-3.1-85EA2D)](https://newsdata.io/openapi.json)

</div>

The official PHP client for the [Newsdata.io](https://newsdata.io) REST API. It
wraps every endpoint (`latest`, `archive`, `sources`, `crypto`, `market`,
`count`, `crypto/count`, `market/count`) with client-side parameter validation,
automatic retries with exponential backoff, and a typed exception hierarchy. It
also covers the real-time WebSocket service: register, list, and delete queries,
and stream the matching news as it is published.

## Requirements

PHP 7.3+ with the `curl` and `json` extensions.

## Installation

With [Composer](https://getcomposer.org/):

```bash
composer require newsdataio/newsdataapi
```

Without Composer, include the bundled autoloader:

```php
require_once '/path/to/php-client/autoload.php';
```

## Quickstart

```php
use NewsdataIO\NewsdataApi;
use NewsdataIO\Exception\NewsdataException;

$client = new NewsdataApi(NEWSDATA_API_KEY);

try {
    $response = $client->get_latest_news([
        'q'        => 'bitcoin',
        'country'  => ['us', 'gb'],   // string or array of strings
        'language' => 'en',
    ]);

    foreach ($response->results as $article) {
        echo $article->title, PHP_EOL;
    }
} catch (NewsdataException $e) {
    echo 'Request failed: ', $e->getMessage(), PHP_EOL;
}
```

Pass `['language' => ['en', 'fr']]` and the array is sent comma-separated.
By default the response is decoded to objects; call
`$client->setDecodeJsonAsArray(true)` to get associative arrays instead.

## Endpoints

| Method | Endpoint | Notes |
|--------|----------|-------|
| `get_latest_news($data)` | `/1/latest` | Real-time news |
| `news_archive($data)` | `/1/archive` | Historical news |
| `news_sources($data)` | `/1/sources` | Available sources |
| `get_crypto_news($data)` | `/1/crypto` | Cryptocurrency news |
| `get_market_news($data)` | `/1/market` | Market / financial news |
| `get_news_count($data)` | `/1/count` | Aggregate counts (requires `from_date`, `to_date`) |
| `get_crypto_count($data)` | `/1/crypto/count` | Aggregate crypto counts (requires dates) |
| `get_market_count($data)` | `/1/market/count` | Aggregate market counts (requires dates) |
| `get_websocket_register($data)` | `/1/websocket/register` | Register a real-time query |
| `get_websocket_fetch()` | `/1/websocket/fetch` | List registered queries |
| `get_websocket_delete($id)` | `/1/websocket/delete` | Delete a registered query |

Each `$data` value may be a single string or an array of strings. Parameter
names are case-insensitive. See the
[Newsdata.io documentation](https://newsdata.io/documentation) — or the
[OpenAPI 3.1 spec](https://newsdata.io/openapi.json) — for the full
parameter reference per endpoint.

```php
$client->get_market_news(['q' => 'apple', 'market_id' => 'AAPL']);

$client->get_news_count([
    'from_date' => '2024-01-01',
    'to_date'   => '2024-01-31',
    'interval'  => 'day',
]);
```

### Raw query

To pass a query string or full URL verbatim, use `raw_query`. It is mutually
exclusive with every other parameter and is validated against the endpoint's
allowed keys:

```php
$client->get_latest_news(['raw_query' => 'q=bitcoin&country=us&language=en']);
```

## Client-side validation

Before any request is sent, parameters are validated and normalized. A
`NewsdataValidationError` is raised (without spending API quota) when:

- a parameter is not accepted by that endpoint;
- mutually-exclusive parameters are set together — `q`/`qInTitle`/`qInMeta`,
  `country`/`excludecountry`, `category`/`excludecategory`,
  `language`/`excludelanguage`, `domain`/`domainurl`/`excludedomain`;
- `size` is outside 1–50;
- `sentiment_score` is set without `sentiment`;
- a count endpoint is missing `from_date` or `to_date`.

Booleans (`full_content`, `image`, `video`, `removeduplicate`) are coerced to
`1` / `0`.

## Real-time news (WebSocket)

Register a query first — the returned `registration_id` identifies it from then on:

```php
use NewsdataIO\NewsdataApi;
use NewsdataIO\NewsdataWebSocket;

$api = new NewsdataApi('YOUR_API_KEY');
$ws  = new NewsdataWebSocket($api);

$registered = $ws->register(['q' => 'bitcoin', 'language' => 'en']);
$registrationId = $registered->results->registration_id;
```

`register()` takes the familiar filter names (`q`, `country`, `language`,
`domain`, …) — no date or paging filters, since a registered query matches news
as it is published. Registering an identical query twice throws
`NewsdataAPIError` with status 409; the existing id is in the response body.
`fetch()` lists every registered query and `delete($id)` removes one. All three
also exist directly on the API object as `get_websocket_register()`,
`get_websocket_fetch()` and `get_websocket_delete()`.

Then stream. `stream()` is a generator — `break` out of the loop to stop, and
the connection closes for you:

```php
foreach ($ws->stream($registrationId) as $response) {
    foreach ($response->results as $article) {
        echo $article->title, ' - ', $article->link, PHP_EOL;
    }
}
```

Transient drops (network errors, server restarts, abnormal closes) are
reconnected automatically with a capped exponential backoff. Pass
`'reconnect' => false` to stop on the first disconnect instead. A permanent
rejection — bad API key or unknown
`registration_id`, exhausted API credits, or too many simultaneous devices — throws
`NewsdataWebSocketAuthError` and is **not** retried.

The server always accepts the handshake and then closes with code **1008** when
the connection is refused, carrying one of three reasons: `invalid credentials
or registration not found`, `api limit reached`, or `device limit reached` (more
than 5 devices on one `registration_id`). Every other close code — including
`1013` (`send timeout`, meaning the client read too slowly) — is transient and
reconnects.

**Each delivered article consumes 1 API credit per connected device.**

Catch it like any other client error:

```php
use NewsdataIO\Exception\NewsdataWebSocketAuthError;
use NewsdataIO\Exception\NewsdataWebSocketError;

try {
    foreach ($ws->stream($registrationId) as $response) {
        // ...
    }
} catch (NewsdataWebSocketAuthError $e) {
    echo 'rejected: ', $e->getMessage(), PHP_EOL;
} catch (NewsdataWebSocketError $e) {
    echo 'stream error: ', $e->getMessage(), PHP_EOL;
}
```

All connection options are optional:

```php
$ws = new NewsdataWebSocket($api, [
    'baseUrl'           => 'wss://ws.newsdata.io/ws/event', // staging / self-hosted
    'reconnect'         => true,   // auto-reconnect on transient drops; default true
    'reconnectDelay'    => 1.0,    // seconds before the first reconnect (doubles each retry)
    'reconnectDelayMax' => 30.0,   // cap on the reconnect delay
    'handshakeTimeout'  => 10,     // seconds to wait for the opening handshake
]);
```

> **Streaming needs one extra package.** PHP has no WebSocket client in core, so
> `stream()` requires [`phrity/websocket`](https://packagist.org/packages/phrity/websocket)
> (PHP 8.1+):
>
> ```bash
> composer require phrity/websocket
> ```
>
> It is an **optional** dependency — everything else in this SDK, including the
> three `websocket/*` management endpoints above, works without it on every
> supported PHP version. `stream()` throws a `NewsdataWebSocketError` telling
> you to install it if it is missing.

Runnable example: [`examples/websocket.php`](examples/websocket.php).

## Error handling

```php
use NewsdataIO\Exception\NewsdataValidationError;
use NewsdataIO\Exception\NewsdataAuthError;
use NewsdataIO\Exception\NewsdataRateLimitError;
use NewsdataIO\Exception\NewsdataAPIError;
use NewsdataIO\Exception\NewsdataNetworkError;

try {
    $client->get_latest_news(['q' => 'news']);
} catch (NewsdataValidationError $e) {
    // bad parameter — $e->getParam()
} catch (NewsdataAuthError $e) {
    // 401 / 403
} catch (NewsdataRateLimitError $e) {
    // 429 — $e->getRetryAfter()
} catch (NewsdataAPIError $e) {
    // other API error — $e->getStatusCode(), $e->getResponseBody()
} catch (NewsdataNetworkError $e) {
    // cURL / connectivity failure
}
```

Hierarchy (all under the `NewsdataIO\Exception` namespace):

```
NewsdataException                       (catch-all base)
├── NewsdataValidationError             (getParam())
├── NewsdataAPIError                    (getStatusCode(), getResponseBody())
│   ├── NewsdataAuthError               (401 / 403)
│   ├── NewsdataRateLimitError          (429; getRetryAfter())
│   └── NewsdataServerError             (5xx)
├── NewsdataNetworkError                (cURL / connectivity)
└── NewsdataWebSocketError              (real-time stream)
    └── NewsdataWebSocketAuthError      (policy-violation close 1008)
```

## Configuration

```php
$client->setTimeouts($connectSeconds = 10, $totalSeconds = 30);
$client->setRetries($maxAttempts = 5, $backoffBaseSeconds = 2.0);
$client->setRetryBackoffMax($seconds = 60.0);
$client->setDecodeJsonAsArray(true);
$client->setProxy([
    'CURLOPT_PROXY'        => 'proxy.example.com',
    'CURLOPT_PROXYPORT'    => 8080,
    'CURLOPT_PROXYUSERPWD' => 'user:pass',
]);
$client->setLogger($psr3Logger);   // API key is redacted from logged URLs
```

Retries cover network errors, HTTP 429, and 5xx responses. 429 honors the
`Retry-After` header (integer seconds or HTTP-date); otherwise backoff is
exponential (`2s → 4s → 8s …`, capped). Auth and other 4xx errors are never
retried.

Response metadata for the most recent call:

```php
$client->getLastResponse()->getHttpCode();
$client->getLastResponse()->getHeaders();
```

## Development

```bash
composer install
composer test        # or: vendor/bin/phpunit
```

The test suite (`tests/`) covers the parameter validator and runs entirely
offline — no API key required.

## Related libraries

Official Newsdata.io clients across languages and runtimes:

- **Python** — [newsdataapi/python-client](https://github.com/newsdataapi/python-client) ([PyPI](https://pypi.org/project/newsdataapi/))
- **Node.js** — [newsdataapi/newsdata-nodejs-client](https://github.com/newsdataapi/newsdata-nodejs-client) ([npm](https://www.npmjs.com/package/newsdata-nodejs-client))
- **React (hooks)** — [newsdataapi/newsdata-reactjs-client](https://github.com/newsdataapi/newsdata-reactjs-client) ([npm](https://www.npmjs.com/package/newsdataapi))
- **Java** — [newsdataapi/newsdata-java-sdk](https://github.com/newsdataapi/newsdata-java-sdk) ([Maven Central](https://central.sonatype.com/artifact/io.newsdata/newsdataapi))
- **.NET** — [newsdataapi/newsdata-dotnet-sdk](https://github.com/newsdataapi/newsdata-dotnet-sdk) ([NuGet](https://www.nuget.org/packages/Newsdata.Api/))
- **Go** — [newsdataapi/newsdata-go-client](https://github.com/newsdataapi/newsdata-go-client) ([pkg.go.dev](https://pkg.go.dev/github.com/newsdataapi/newsdata-go-client))
- **Dart / Flutter** — [newsdataapi/newsdata-flutter-client](https://github.com/newsdataapi/newsdata-flutter-client) ([pub.dev](https://pub.dev/packages/newsdataapi))
- **MCP Server (AI assistants)** — [newsdataapi/newsdata.io-mcp](https://github.com/newsdataapi/newsdata.io-mcp) ([PyPI](https://pypi.org/project/newsdata-mcp/))

Also see [free news datasets](https://github.com/newsdataapi/newsdata.io-free-datasets) for ML / NLP work.

## License

[MIT](https://github.com/newsdataapi/php-client/blob/main/LICENSE).
