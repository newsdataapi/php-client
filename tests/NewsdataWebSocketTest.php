<?php

declare(strict_types=1);

namespace NewsdataIO\Tests;

use NewsdataIO\Constants;
use NewsdataIO\Exception\NewsdataValidationError;
use NewsdataIO\Exception\NewsdataWebSocketAuthError;
use NewsdataIO\Exception\NewsdataWebSocketError;
use NewsdataIO\NewsdataApi;
use NewsdataIO\NewsdataWebSocket;
use PHPUnit\Framework\TestCase;

/**
 * A scriptable stand-in for a live WebSocket connection. The script is a list
 * of actions: a string is delivered as a frame, null closes the connection,
 * and a Throwable is raised.
 */
final class FakeConnection
{
    /** @var array<int,mixed> */
    private $script;

    /** @var int */
    public $connectionNumber;

    /** @var bool */
    public $closed = false;

    public function __construct(array $script, int $connectionNumber)
    {
        $this->script = $script;
        $this->connectionNumber = $connectionNumber;
    }

    /**
     * @return string|null
     */
    public function receive()
    {
        if ($this->script === []) {
            return null; // nothing left: behave as a closed connection
        }
        $next = array_shift($this->script);
        if ($next instanceof \Throwable) {
            throw $next;
        }
        return $next; // string frame, or null to close
    }

    public function close(): void
    {
        $this->closed = true;
    }
}

/**
 * Real-time WebSocket tests. The transport is injected via the `connector`
 * option, so these run without a live server or the phrity/websocket package.
 */
class NewsdataWebSocketTest extends TestCase
{
    /** @var array<int,FakeConnection> */
    public $connections = [];

    /** @var array<int,string> */
    public $urls = [];

    private function api(): NewsdataApi
    {
        return new NewsdataApi('key');
    }

    /**
     * Build a websocket whose connector hands out scripted fake connections.
     *
     * @param callable $scriptFor fn(int $connectionNumber): array
     */
    private function ws(callable $scriptFor, array $options = []): NewsdataWebSocket
    {
        $this->connections = [];
        $this->urls = [];

        $options['connector'] = function (string $url) use ($scriptFor) {
            $this->urls[] = $url;
            $n = count($this->connections) + 1;
            $conn = new FakeConnection($scriptFor($n), $n);
            $this->connections[] = $conn;
            return $conn;
        };
        $options['reconnectDelay'] = $options['reconnectDelay'] ?? 0.001;
        $options['reconnectDelayMax'] = $options['reconnectDelayMax'] ?? 0.002;

        return new NewsdataWebSocket($this->api(), $options);
    }

    private static function articleFrame(string $id, string $title): string
    {
        return json_encode([
            'status' => 'success',
            'totalResults' => 1,
            'results' => [['article_id' => $id, 'title' => $title]],
        ]);
    }

    public function testStreamYieldsEachResponse(): void
    {
        $ws = $this->ws(fn (int $n) => [
            self::articleFrame('a1', 'one'),
            self::articleFrame('a2', 'two'),
        ], ['reconnect' => false]);

        $titles = [];
        foreach ($ws->stream('reg-1') as $response) {
            $titles[] = $response->results[0]->title;
            if (count($titles) === 2) {
                break;
            }
        }

        $this->assertSame(['one', 'two'], $titles);
    }

    public function testStreamSendsApiKeyAndRegistrationIdInQuery(): void
    {
        $ws = $this->ws(fn (int $n) => [self::articleFrame('a1', 'one')], ['reconnect' => false]);

        foreach ($ws->stream('reg-42') as $response) {
            break;
        }

        $this->assertStringContainsString('apikey=key', $this->urls[0]);
        $this->assertStringContainsString('registration_id=reg-42', $this->urls[0]);
    }

    public function testStreamSkipsMalformedFrames(): void
    {
        $ws = $this->ws(fn (int $n) => [
            'not json at all',
            self::articleFrame('a1', 'one'),
        ], ['reconnect' => false]);

        $seen = [];
        foreach ($ws->stream('reg-1') as $response) {
            $seen[] = $response->results[0]->title;
            break;
        }

        $this->assertSame(['one'], $seen, 'the malformed frame should be skipped');
    }

    public function testPolicyViolationCloseIsPermanentAndNotRetried(): void
    {
        // reconnect stays ON to prove a permanent rejection is not retried.
        $ws = $this->ws(fn (int $n) => [
            new \RuntimeException('quota exhausted', Constants::WS_POLICY_VIOLATION),
        ]);

        try {
            foreach ($ws->stream('reg-1') as $response) {
                $this->fail('should not yield');
            }
            $this->fail('expected a NewsdataWebSocketAuthError');
        } catch (NewsdataWebSocketAuthError $e) {
            $this->assertStringContainsString('quota exhausted', $e->getMessage());
        }

        $this->assertCount(1, $this->connections, 'a permanent rejection must not retry');
    }

    public function testHandshake401IsPermanent(): void
    {
        $ws = $this->ws(fn (int $n) => [
            new \RuntimeException('Could not connect: server responded 401'),
        ]);

        $this->expectException(NewsdataWebSocketAuthError::class);
        foreach ($ws->stream('reg-1') as $response) {
            $this->fail('should not yield');
        }
    }

    public function testTransientFailureStopsWhenReconnectDisabled(): void
    {
        $ws = $this->ws(fn (int $n) => [
            new \RuntimeException('connection refused'),
        ], ['reconnect' => false]);

        try {
            foreach ($ws->stream('reg-1') as $response) {
                $this->fail('should not yield');
            }
            $this->fail('expected a NewsdataWebSocketError');
        } catch (NewsdataWebSocketAuthError $e) {
            $this->fail('a plain connection error should not be an auth error');
        } catch (NewsdataWebSocketError $e) {
            $this->assertStringContainsString('connection refused', $e->getMessage());
        }
    }

    public function testReconnectsAfterTransientDrop(): void
    {
        $ws = $this->ws(function (int $n) {
            if ($n === 1) {
                return [new \RuntimeException('connection reset')]; // transient
            }
            return [self::articleFrame('a1', 'after-reconnect')];
        });

        $titles = [];
        foreach ($ws->stream('reg-1') as $response) {
            $titles[] = $response->results[0]->title;
            break;
        }

        $this->assertSame(['after-reconnect'], $titles);
        $this->assertGreaterThanOrEqual(2, count($this->connections));
    }

    public function testStreamRejectsEmptyRegistrationId(): void
    {
        $ws = $this->ws(fn (int $n) => []);

        $this->expectException(NewsdataValidationError::class);
        foreach ($ws->stream('') as $response) {
            $this->fail('should not yield');
        }
    }

    public function testBreakingOutOfTheLoopClosesTheConnection(): void
    {
        $ws = $this->ws(fn (int $n) => [
            self::articleFrame('a1', 'one'),
            self::articleFrame('a2', 'two'),
        ], ['reconnect' => false]);

        foreach ($ws->stream('reg-1') as $response) {
            break;
        }

        $this->assertTrue($this->connections[0]->closed);
    }

    // ---- query management -------------------------------------------------

    public function testRegisterInjectsNewsTypeIntoFilters(): void
    {
        // websocket_register accepts news_type; validation proves it is set.
        $filters = Constants::FILTERS['websocket_register'];
        $this->assertContains('news_type', $filters);
        $this->assertContains('q', $filters);
        // No date or paging filters on a registered query.
        $this->assertNotContains('from_date', $filters);
        $this->assertNotContains('page', $filters);
        $this->assertNotContains('size', $filters);
    }

    public function testWebsocketEndpointsUseTheRightHttpMethods(): void
    {
        $this->assertSame('POST', Constants::ENDPOINT_METHODS['websocket_register']);
        $this->assertSame('DELETE', Constants::ENDPOINT_METHODS['websocket_delete']);
        // fetch is absent, so it falls through to GET.
        $this->assertArrayNotHasKey('websocket_fetch', Constants::ENDPOINT_METHODS);
    }

    public function testWebsocketEndpointPathsAreRegistered(): void
    {
        $this->assertSame('websocket/register', Constants::ENDPOINTS['websocket_register']);
        $this->assertSame('websocket/fetch', Constants::ENDPOINTS['websocket_fetch']);
        $this->assertSame('websocket/delete', Constants::ENDPOINTS['websocket_delete']);
    }

    public function testDeleteRejectsEmptyRegistrationId(): void
    {
        $this->expectException(NewsdataValidationError::class);
        $this->api()->get_websocket_delete('');
    }
}
