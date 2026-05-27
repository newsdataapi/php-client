<?php

declare(strict_types=1);

namespace NewsdataIO;

use NewsdataIO\Exception\NewsdataAPIError;
use NewsdataIO\Exception\NewsdataAuthError;
use NewsdataIO\Exception\NewsdataException;
use NewsdataIO\Exception\NewsdataNetworkError;
use NewsdataIO\Exception\NewsdataRateLimitError;
use NewsdataIO\Exception\NewsdataServerError;

/**
 * Transport layer for the Newsdata.io client.
 *
 * Owns the HTTP concerns: validating parameters, building the request URL,
 * executing it with retries and exponential backoff, parsing the response,
 * and mapping failures onto the typed exception hierarchy. The public
 * endpoint methods live in {@see NewsdataApi}.
 */
class NewsdataApiBase
{
    /** @var string */
    protected $apiKey;

    /** @var Response Details of the most recent request. */
    protected $response;

    /** @var int Seconds to wait for a complete response. */
    protected $timeout = Constants::DEFAULT_REQUEST_TIMEOUT;

    /** @var int Seconds to wait while connecting. */
    protected $connectionTimeout = Constants::DEFAULT_CONNECT_TIMEOUT;

    /** @var int Total request attempts (1 = no retry). */
    protected $maxRetries = Constants::DEFAULT_MAX_RETRIES;

    /** @var float Base seconds for exponential backoff. */
    protected $retryBackoff = Constants::DEFAULT_RETRY_BACKOFF;

    /** @var float Cap on a single backoff sleep, in seconds. */
    protected $retryBackoffMax = Constants::DEFAULT_RETRY_BACKOFF_MAX;

    /** @var bool Decode JSON responses as associative arrays. */
    protected $decodeJsonAsArray = false;

    /** @var array cURL proxy configuration. */
    protected $proxy = [];

    /** @var \Psr\Log\LoggerInterface|null Optional PSR-3 logger. */
    protected $logger;

    /**
     * @param string $apiKey
     */
    public function __construct(string $apiKey)
    {
        if ($apiKey === '') {
            throw new Exception\NewsdataValidationError('apikey must be a non-empty string', 'apikey');
        }
        $this->apiKey = $apiKey;
        $this->response = new Response();
    }

    /**
     * Metadata (status code, headers) of the most recent request.
     */
    public function getLastResponse(): Response
    {
        return $this->response;
    }

    /**
     * Validate parameters and execute a request against the named endpoint.
     *
     * @param string $endpoint One of {@see Constants::ENDPOINTS}.
     * @param array  $data      Raw user parameters.
     *
     * @return array|object Decoded response body.
     */
    protected function request(string $endpoint, array $data)
    {
        if (!isset(Constants::ENDPOINTS[$endpoint])) {
            throw new Exception\NewsdataValidationError("Unknown endpoint: {$endpoint}");
        }
        $params = ParamValidator::validate($endpoint, $data);
        $baseUrl = Constants::BASE_URL . Constants::ENDPOINTS[$endpoint];
        return $this->execute($baseUrl, $params);
    }

    /**
     * Execute a single GET with retries and backoff.
     *
     * @param string                $baseUrl
     * @param array<string,string>  $params  Validated, url-encodable params.
     *
     * @return array|object
     */
    private function execute(string $baseUrl, array $params)
    {
        $this->response = new Response();
        $this->response->setApiPath($baseUrl);

        $query = Util::buildHttpQuery($params);
        $fullUrl = $baseUrl . '?apikey=' . rawurlencode($this->apiKey)
            . ($query !== '' ? '&' . $query : '');
        $logUrl = Util::redactApiKey($fullUrl);

        $attempts = max(1, $this->maxRetries);

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $this->log('info', "GET {$logUrl} (attempt {$attempt}/{$attempts})");

            try {
                [$status, $headers, $rawBody] = $this->httpGet($fullUrl);
            } catch (NewsdataNetworkError $e) {
                if ($attempt >= $attempts) {
                    throw $e;
                }
                $this->log('warning', "network error: {$e->getMessage()}");
                $this->sleepSeconds($this->backoff($attempt));
                continue;
            }

            $this->response->setHttpCode($status);
            $this->response->setHeaders($headers);

            $body = $this->jsonDecode($rawBody);
            if (json_last_error() !== JSON_ERROR_NONE) {
                if ($status >= 500 && $attempt < $attempts) {
                    $this->log('warning', "non-JSON response (status {$status})");
                    $this->sleepSeconds($this->backoff($attempt));
                    continue;
                }
                throw new NewsdataAPIError(
                    "Non-JSON response from API (status {$status})",
                    $status
                );
            }
            $this->response->setBody($body);

            if ($status === 200 && $this->isSuccessBody($body)) {
                return $body;
            }

            if ($status === 429) {
                $retryAfter = $this->parseRetryAfter(
                    isset($headers['retry_after']) ? $headers['retry_after'] : null
                );
                if ($attempt >= $attempts) {
                    throw new NewsdataRateLimitError(
                        $this->errorMessage($body, $status),
                        429,
                        $this->toArray($body),
                        $retryAfter
                    );
                }
                $sleep = $retryAfter !== null ? (float) $retryAfter : $this->backoff($attempt);
                $this->log('warning', "429 rate limit; sleeping {$sleep}s");
                $this->sleepSeconds($sleep);
                continue;
            }

            if ($status >= 500) {
                if ($attempt >= $attempts) {
                    throw new NewsdataServerError(
                        $this->errorMessage($body, $status),
                        $status,
                        $this->toArray($body)
                    );
                }
                $this->log('warning', "{$status} server error");
                $this->sleepSeconds($this->backoff($attempt));
                continue;
            }

            if ($status === 401 || $status === 403) {
                throw new NewsdataAuthError(
                    $this->errorMessage($body, $status),
                    $status,
                    $this->toArray($body)
                );
            }

            // Any other 4xx — never retried.
            throw new NewsdataAPIError(
                $this->errorMessage($body, $status),
                $status,
                $this->toArray($body)
            );
        }

        // Defensive: the loop always returns or throws above.
        throw new NewsdataException(
            "Request to {$baseUrl} did not complete (maxRetries={$this->maxRetries})"
        );
    }

    /**
     * Perform one cURL GET.
     *
     * @param string $url
     *
     * @return array{0:int,1:array,2:string} [status, headers, body]
     *
     * @throws NewsdataNetworkError
     */
    private function httpGet(string $url): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, $this->curlOptions($url));

        $raw = curl_exec($ch);

        if ($raw === false || curl_errno($ch) > 0) {
            $errNo = curl_errno($ch);
            $errMsg = curl_error($ch);
            curl_close($ch);
            throw new NewsdataNetworkError(
                "cURL error ({$errNo}): {$errMsg}",
                $errNo
            );
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $rawHeaders = substr((string) $raw, 0, $headerSize);
        $body = substr((string) $raw, $headerSize);

        return [$status, $this->parseHeaders($rawHeaders), $body];
    }

    /**
     * @param string $url
     *
     * @return array
     */
    private function curlOptions(string $url): array
    {
        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_CONNECTTIMEOUT => $this->connectionTimeout,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HEADER         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_ENCODING       => '',
        ];

        if (!empty($this->proxy)) {
            $options[CURLOPT_PROXY]        = $this->proxy['CURLOPT_PROXY'] ?? '';
            $options[CURLOPT_PROXYUSERPWD] = $this->proxy['CURLOPT_PROXYUSERPWD'] ?? '';
            $options[CURLOPT_PROXYPORT]    = $this->proxy['CURLOPT_PROXYPORT'] ?? 0;
            $options[CURLOPT_PROXYAUTH]    = CURLAUTH_BASIC;
            $options[CURLOPT_PROXYTYPE]    = CURLPROXY_HTTP;
        }

        return $options;
    }

    /**
     * Parse a raw header block into a lowercase, underscore-keyed map.
     *
     * @param string $header
     *
     * @return array
     */
    private function parseHeaders(string $header): array
    {
        $headers = [];
        foreach (explode("\r\n", $header) as $line) {
            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $key = str_replace('-', '_', strtolower(trim($key)));
                $headers[$key] = trim($value);
            }
        }
        return $headers;
    }

    /**
     * @param string $json
     *
     * @return array|object|null
     */
    private function jsonDecode(string $json)
    {
        if (
            version_compare(PHP_VERSION, '5.4.0', '>=')
            && !(defined('JSON_C_VERSION') && PHP_INT_SIZE > 4)
        ) {
            return json_decode($json, $this->decodeJsonAsArray, 512, JSON_BIGINT_AS_STRING);
        }
        return json_decode($json, $this->decodeJsonAsArray);
    }

    /**
     * A successful payload is HTTP 200 with status="success" and non-null
     * results, in either array or object decode mode.
     *
     * @param mixed $body
     */
    private function isSuccessBody($body): bool
    {
        if (is_array($body)) {
            return ($body['status'] ?? null) === 'success'
                && array_key_exists('results', $body)
                && $body['results'] !== null;
        }
        if ($body instanceof \stdClass) {
            return isset($body->status) && $body->status === 'success'
                && property_exists($body, 'results') && $body->results !== null;
        }
        return false;
    }

    /**
     * @param mixed $body
     *
     * @return array|null
     */
    private function toArray($body): ?array
    {
        if (is_array($body)) {
            return $body;
        }
        if ($body instanceof \stdClass) {
            $decoded = json_decode((string) json_encode($body), true);
            return is_array($decoded) ? $decoded : null;
        }
        return null;
    }

    /**
     * Extract a human-readable error message from an API error body.
     *
     * @param mixed $body
     * @param int   $status
     */
    private function errorMessage($body, int $status): string
    {
        $arr = $this->toArray($body);
        if (is_array($arr)) {
            if (isset($arr['results']['message'])) {
                return (string) $arr['results']['message'];
            }
            if (isset($arr['message'])) {
                return (string) $arr['message'];
            }
        }
        return "API request failed with HTTP {$status}";
    }

    /**
     * Parse a Retry-After header (integer seconds or HTTP-date) into seconds.
     *
     * @param string|null $value
     *
     * @return int|null
     */
    private function parseRetryAfter(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (ctype_digit($value)) {
            return max(0, (int) $value);
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }
        return max(0, $timestamp - time());
    }

    /**
     * Exponential backoff for the given attempt, capped at the configured max.
     */
    private function backoff(int $attempt): float
    {
        $delay = $this->retryBackoff * (2 ** ($attempt - 1));
        return min($delay, $this->retryBackoffMax);
    }

    private function sleepSeconds(float $seconds): void
    {
        if ($seconds > 0) {
            usleep((int) round($seconds * 1000000));
        }
    }

    /**
     * @param string $level   PSR-3 log level.
     * @param string $message
     */
    private function log(string $level, string $message): void
    {
        if ($this->logger !== null) {
            $this->logger->log($level, '[newsdataapi] ' . $message);
        }
    }
}
