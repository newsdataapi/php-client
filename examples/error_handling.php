<?php

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/config.php';

use NewsdataIO\NewsdataApi;
use NewsdataIO\Exception\NewsdataAuthError;
use NewsdataIO\Exception\NewsdataRateLimitError;
use NewsdataIO\Exception\NewsdataValidationError;
use NewsdataIO\Exception\NewsdataAPIError;
use NewsdataIO\Exception\NewsdataNetworkError;

$client = new NewsdataApi(NEWSDATA_API_KEY);

// Optional tuning.
$client->setTimeouts(10, 30);   // connect, total (seconds)
$client->setRetries(5, 2.0);    // attempts, backoff base (seconds)

try {
    $response = $client->get_latest_news(['q' => 'bitcoin', 'country' => 'us']);
    var_dump($response);

    // Response metadata (HTTP status, headers) for the last request:
    echo 'HTTP ' . $client->getLastResponse()->getHttpCode() . PHP_EOL;
} catch (NewsdataValidationError $e) {
    echo "Invalid parameter ({$e->getParam()}): {$e->getMessage()}" . PHP_EOL;
} catch (NewsdataAuthError $e) {
    echo "Auth failed (HTTP {$e->getStatusCode()})" . PHP_EOL;
} catch (NewsdataRateLimitError $e) {
    echo "Rate limited; retry after {$e->getRetryAfter()}s" . PHP_EOL;
} catch (NewsdataAPIError $e) {
    echo "API error {$e->getStatusCode()}: {$e->getMessage()}" . PHP_EOL;
} catch (NewsdataNetworkError $e) {
    echo "Network failure: {$e->getMessage()}" . PHP_EOL;
}
