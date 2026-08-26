<?php

declare(strict_types=1);

namespace NewsdataIO;

/**
 * Official PHP client for the Newsdata.io REST API.
 *
 * Each endpoint method takes an associative array of parameters (a single
 * value or an array of values per key) and returns the decoded response body.
 * Parameters are validated client-side before the request is sent; failures
 * raise the typed exceptions in the {@see \NewsdataIO\Exception} namespace.
 *
 * Example:
 *
 *     use NewsdataIO\NewsdataApi;
 *
 *     $client = new NewsdataApi(NEWSDATA_API_KEY);
 *     $response = $client->get_latest_news(['q' => 'bitcoin', 'country' => 'us']);
 */
class NewsdataApi extends NewsdataApiBase
{
    /**
     * @param string $api_key Your private Newsdata.io API key.
     */
    public function __construct(string $api_key)
    {
        parent::__construct($api_key);
    }

    // ---- configuration ----------------------------------------------------

    /**
     * Set the connect and overall response timeouts (seconds).
     */
    public function setTimeouts(int $connectionTimeout, int $timeout): void
    {
        $this->connectionTimeout = $connectionTimeout;
        $this->timeout = $timeout;
    }

    /**
     * Configure retry behaviour.
     *
     * @param int   $maxRetries   Total attempts (1 = no retry).
     * @param float $retryBackoff Base seconds for exponential backoff.
     */
    public function setRetries(int $maxRetries, float $retryBackoff = Constants::DEFAULT_RETRY_BACKOFF): void
    {
        $this->maxRetries = max(1, $maxRetries);
        $this->retryBackoff = $retryBackoff;
    }

    /**
     * Cap the longest single backoff sleep (seconds).
     */
    public function setRetryBackoffMax(float $seconds): void
    {
        $this->retryBackoffMax = $seconds;
    }

    /**
     * Decode JSON responses as associative arrays instead of objects.
     */
    public function setDecodeJsonAsArray(bool $value): void
    {
        $this->decodeJsonAsArray = $value;
    }

    /**
     * @param array $proxy cURL proxy settings (CURLOPT_PROXY, CURLOPT_PROXYUSERPWD, CURLOPT_PROXYPORT).
     */
    public function setProxy(array $proxy): void
    {
        $this->proxy = $proxy;
    }

    /**
     * Attach a PSR-3 logger. The API key is redacted from logged URLs.
     *
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function setLogger($logger): void
    {
        $this->logger = $logger;
    }

    // ---- endpoints --------------------------------------------------------

    /**
     * Latest news. GET /1/latest
     *
     * @param array $data
     *
     * @return array|object
     */
    public function get_latest_news(array $data = [])
    {
        return $this->request('latest', $data);
    }

    /**
     * Cryptocurrency news. GET /1/crypto
     *
     * @param array $data
     *
     * @return array|object
     */
    public function get_crypto_news(array $data = [])
    {
        return $this->request('crypto', $data);
    }

    /**
     * Historical news. GET /1/archive
     *
     * @param array $data
     *
     * @return array|object
     */
    public function news_archive(array $data = [])
    {
        return $this->request('archive', $data);
    }

    /**
     * Available news sources. GET /1/sources
     *
     * @param array $data
     *
     * @return array|object
     */
    public function news_sources(array $data = [])
    {
        return $this->request('sources', $data);
    }

    /**
     * Market / financial news. GET /1/market
     *
     * @param array $data
     *
     * @return array|object
     */
    public function get_market_news(array $data = [])
    {
        return $this->request('market', $data);
    }

    /**
     * Aggregate news counts for a date range. GET /1/count
     * Requires `from_date` and `to_date`.
     *
     * @param array $data
     *
     * @return array|object
     */
    public function get_news_count(array $data = [])
    {
        return $this->request('count', $data);
    }

    /**
     * Aggregate crypto news counts for a date range. GET /1/crypto/count
     * Requires `from_date` and `to_date`.
     *
     * @param array $data
     *
     * @return array|object
     */
    public function get_crypto_count(array $data = [])
    {
        return $this->request('crypto_count', $data);
    }

    /**
     * Aggregate market news counts for a date range. GET /1/market/count
     * Requires `from_date` and `to_date`.
     *
     * @param array $data
     *
     * @return array|object
     */
    public function get_market_count(array $data = [])
    {
        return $this->request('market_count', $data);
    }

    // ---- real-time query management ---------------------------------------

    /**
     * Register a real-time WebSocket query. POST /1/websocket/register.
     *
     * Takes the familiar filter names (`q`, `country`, `language`, `domain`, …);
     * no date or paging filters apply, since a registered query matches news as
     * it is published. The new query's id is at `results.registration_id` —
     * pass it to {@see NewsdataWebSocket::stream()}.
     *
     * Registering an identical query twice throws
     * {@see Exception\NewsdataAPIError} with status 409; the existing id is in
     * the response body.
     *
     * @param array $data
     *
     * @return array|object
     */
    public function get_websocket_register(array $data = [])
    {
        $data['news_type'] = Constants::WS_NEWS_TYPE;
        return $this->request('websocket_register', $data);
    }

    /**
     * List the account's registered real-time queries.
     * GET /1/websocket/fetch. One entry per query at `results.queries`.
     *
     * @return array|object
     */
    public function get_websocket_fetch()
    {
        return $this->request('websocket_fetch', []);
    }

    /**
     * Delete a registered real-time query. DELETE /1/websocket/delete.
     *
     * @param string $registrationId
     *
     * @return array|object
     */
    public function get_websocket_delete(string $registrationId)
    {
        if ($registrationId === '') {
            throw new Exception\NewsdataValidationError(
                'registrationId must be a non-empty string',
                'registration_id'
            );
        }
        return $this->request('websocket_delete', ['registration_id' => $registrationId]);
    }

    /** The API key, for the WebSocket handshake URL. @internal */
    public function apiKeyForWebSocket(): string
    {
        return $this->apiKey;
    }

    /**
     * Whether responses decode to arrays rather than objects; the WebSocket
     * layer decodes frames the same way.
     *
     * @internal
     */
    public function isDecodingJsonAsArray(): bool
    {
        return $this->decodeJsonAsArray;
    }
}
