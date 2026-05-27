<div align="center">

![Newsdata.io logo](https://raw.githubusercontent.com/newsdataapi/php-client/main/newsdata-logo.png)

# Newsdata.io PHP Client

[![License](https://img.shields.io/badge/license-MIT-blue)](https://github.com/newsdataapi/php-client/blob/main/LICENSE)
[![PHP](https://img.shields.io/badge/php-%5E7.3%20%7C%7C%20%5E8.0-green?logo=php)](https://github.com/newsdataapi/php-client/blob/main/LICENSE)

</div>

The official PHP client for the [Newsdata.io](https://newsdata.io) REST API. It
wraps every endpoint (`latest`, `archive`, `sources`, `crypto`, `market`,
`count`, `crypto/count`, `market/count`) with client-side parameter validation,
automatic retries with exponential backoff, and a typed exception hierarchy.

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

Each `$data` value may be a single string or an array of strings. Parameter
names are case-insensitive. See the
[Newsdata.io documentation](https://newsdata.io/documentation) for the full
parameter reference per endpoint.

```php
$client->get_market_news(['q' => 'apple', 'symbol' => 'AAPL']);

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
└── NewsdataNetworkError                (cURL / connectivity)
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

## License

[MIT](https://github.com/newsdataapi/php-client/blob/main/LICENSE).
