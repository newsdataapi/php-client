<?php

declare(strict_types=1);

namespace NewsdataIO;

use NewsdataIO\Exception\NewsdataValidationError;
use NewsdataIO\Exception\NewsdataWebSocketAuthError;
use NewsdataIO\Exception\NewsdataWebSocketError;

/**
 * NewsData.io real-time WebSocket service.
 *
 * Registers, lists, and deletes the account's real-time queries (delegating to
 * {@see NewsdataApi}) and streams the responses for a registered query:
 *
 *     $api = new NewsdataApi('YOUR_API_KEY');
 *     $ws  = new NewsdataWebSocket($api);
 *
 *     $registered = $ws->register(['q' => 'bitcoin']);
 *     $id = $registered['results']['registration_id'];
 *
 *     foreach ($ws->stream($id) as $response) {
 *         foreach ($response['results'] as $article) {
 *             echo $article['title'], PHP_EOL;
 *         }
 *     }
 *
 * `stream()` is a generator: `break` out of the loop to stop, and the
 * connection is closed for you.
 *
 * Transient drops (network errors, server restarts, abnormal closes) are
 * reconnected automatically with a capped exponential backoff; pass
 * `'reconnect' => false` to stop on the first disconnect. A permanent
 * rejection always throws {@see NewsdataWebSocketAuthError} and is never
 * retried.
 *
 * Streaming needs the optional `phrity/websocket` package (PHP 8.1+):
 *
 *     composer require phrity/websocket
 *
 * The REST management calls below work without it, on every supported PHP.
 */
class NewsdataWebSocket
{
    /** @var NewsdataApi */
    private $api;

    /** @var string */
    private $baseUrl;

    /** @var bool */
    private $reconnect;

    /** @var float */
    private $reconnectDelay;

    /** @var float */
    private $reconnectDelayMax;

    /** @var int */
    private $handshakeTimeout;

    /** @var callable|null Injection point for tests. */
    private $connector;

    /** @var bool */
    private $closed = false;

    /** @var mixed The live connection, while one is open. */
    private $connection;

    /**
     * @param NewsdataApi $api     Supplies the API key and performs the
     *                             management HTTP calls. Not closed by this
     *                             class.
     * @param array       $options baseUrl, reconnect, reconnectDelay,
     *                             reconnectDelayMax, handshakeTimeout,
     *                             connector (callable, for tests).
     */
    public function __construct(NewsdataApi $api, array $options = [])
    {
        $this->api = $api;
        $this->baseUrl = $options['baseUrl'] ?? Constants::WS_BASE_URL;
        $this->reconnect = $options['reconnect'] ?? true;
        $this->reconnectDelay = (float) ($options['reconnectDelay'] ?? Constants::WS_RECONNECT_DELAY);
        $this->reconnectDelayMax = (float) ($options['reconnectDelayMax'] ?? Constants::WS_RECONNECT_DELAY_MAX);
        $this->handshakeTimeout = (int) ($options['handshakeTimeout'] ?? Constants::WS_HANDSHAKE_TIMEOUT);
        $this->connector = $options['connector'] ?? null;
    }

    // ---- query management -------------------------------------------------

    /**
     * Register a real-time query.
     *
     * @see NewsdataApi::get_websocket_register()
     *
     * @param array $data
     *
     * @return array|object
     */
    public function register(array $data = [])
    {
        return $this->api->get_websocket_register($data);
    }

    /**
     * List the account's registered real-time queries.
     *
     * @see NewsdataApi::get_websocket_fetch()
     *
     * @return array|object
     */
    public function fetch()
    {
        return $this->api->get_websocket_fetch();
    }

    /**
     * Delete a registered real-time query.
     *
     * @see NewsdataApi::get_websocket_delete()
     *
     * @param string $registrationId
     *
     * @return array|object
     */
    public function delete(string $registrationId)
    {
        return $this->api->get_websocket_delete($registrationId);
    }

    // ---- streaming --------------------------------------------------------

    /**
     * Build the handshake URL.
     */
    private function url(string $registrationId): string
    {
        return $this->baseUrl
            . '?apikey=' . rawurlencode($this->api->apiKeyForWebSocket())
            . '&registration_id=' . rawurlencode($registrationId);
    }

    private function nextDelay(float $delay): float
    {
        return min($delay * 2, $this->reconnectDelayMax);
    }

    /**
     * Connect and yield each response for $registrationId as it arrives.
     *
     * Responses have the familiar `status` / `totalResults` / `results` shape,
     * decoded the same way the REST methods decode theirs.
     *
     * @param string $registrationId
     *
     * @return \Generator<int, array|object>
     */
    public function stream(string $registrationId): \Generator
    {
        if ($registrationId === '') {
            throw new NewsdataValidationError(
                'registrationId must be a non-empty string',
                'registration_id'
            );
        }

        $url = $this->url($registrationId);
        $delay = $this->reconnectDelay;
        $this->closed = false;

        try {
            while (!$this->isClosed()) {
                $client = null;

                try {
                    $client = $this->connect($url);
                    $this->connection = $client;

                    while (!$this->isClosed()) {
                        $message = $client->receive();
                        if ($message === null) {
                            break; // connection closed
                        }
                        $payload = is_string($message)
                            ? $message
                            : (is_object($message) && method_exists($message, 'getContent')
                                ? (string) $message->getContent()
                                : null);
                        if ($payload === null) {
                            continue; // ignore binary / control frames
                        }
                        $decoded = $this->decode($payload);
                        if ($decoded !== null) {
                            yield $decoded;
                        }
                        $delay = $this->reconnectDelay; // reset after real traffic
                    }
                } catch (\Throwable $e) {
                    if ($this->isClosed()) {
                        return;
                    }
                    $auth = $this->permanentAuthError($e);
                    if ($auth !== null) {
                        throw $auth;
                    }
                    if (!$this->reconnect) {
                        throw $this->transientError($e);
                    }
                } finally {
                    $this->closeConnection($client);
                }

                if ($this->isClosed()) {
                    return;
                }
                if (!$this->reconnect) {
                    return;
                }
                usleep((int) round($delay * 1000000));
                $delay = $this->nextDelay($delay);
            }
        } finally {
            $this->close();
        }
    }

    /**
     * Open one connection. Uses the injected connector when present (tests),
     * otherwise the phrity/websocket client.
     *
     * @return mixed
     */
    private function connect(string $url)
    {
        if ($this->connector !== null) {
            return ($this->connector)($url);
        }

        // Referenced dynamically: phrity/websocket is an optional dependency,
        // so the class need not exist for this file to load or analyse.
        $clientClass = 'WebSocket\\Client';
        $uriClass = 'Phrity\\Net\\Uri';

        if (!class_exists($clientClass) || !class_exists($uriClass)) {
            throw new NewsdataWebSocketError(
                'Real-time streaming needs the phrity/websocket package: '
                . 'composer require phrity/websocket'
            );
        }

        $client = new $clientClass(new $uriClass($url));
        $client->setTimeout($this->handshakeTimeout);
        $client->connect();
        return $client;
    }

    /**
     * Decode one frame, returning null when it isn't a JSON object.
     *
     * @return array|object|null
     */
    private function decode(string $payload)
    {
        $decoded = json_decode($payload, $this->api->isDecodingJsonAsArray());
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null; // skip malformed frames
        }
        if (!is_array($decoded) && !($decoded instanceof \stdClass)) {
            return null;
        }
        return $decoded;
    }

    /**
     * The auth error to throw if the failure is a permanent rejection,
     * else null (the caller then treats it as transient and reconnects).
     *
     * Close code 1008 and handshake 401 / 403 are permanent; everything else
     * is transient.
     */
    private function permanentAuthError(\Throwable $e): ?NewsdataWebSocketAuthError
    {
        if ($e instanceof NewsdataWebSocketAuthError) {
            return $e;
        }
        $code = $e->getCode();
        if ($code === Constants::WS_POLICY_VIOLATION) {
            $reason = $e->getMessage();
            return new NewsdataWebSocketAuthError(
                $reason !== '' ? $reason : 'connection rejected'
            );
        }
        $message = $e->getMessage();
        if (strpos($message, '401') !== false || strpos($message, '403') !== false) {
            return new NewsdataWebSocketAuthError('connection rejected');
        }
        return null;
    }

    /** Wrap a transient failure; used only when reconnect is disabled. */
    private function transientError(\Throwable $e): NewsdataWebSocketError
    {
        if ($e instanceof NewsdataWebSocketError) {
            return $e;
        }
        return new NewsdataWebSocketError('connection error: ' . $e->getMessage());
    }

    /**
     * @param mixed $client
     */
    private function closeConnection($client): void
    {
        $this->connection = null;
        if ($client === null) {
            return;
        }
        try {
            if (method_exists($client, 'close')) {
                $client->close();
            } elseif (method_exists($client, 'disconnect')) {
                $client->disconnect();
            }
        } catch (\Throwable $ignored) {
            // already closing
        }
    }

    /**
     * Whether {@see close()} has been called.
     *
     * Marked impure so static analysis does not fold the flag to its last
     * assigned value: the stream loop yields, and the caller may close the
     * connection from outside between yields.
     *
     * @phpstan-impure
     */
    private function isClosed(): bool
    {
        return $this->closed;
    }

    /** Close the active connection, ending any in-flight {@see stream()}. */
    public function close(): void
    {
        $this->closed = true;
        $this->closeConnection($this->connection);
    }
}
