<?php

declare(strict_types=1);

namespace NewsdataIO\Tests;

use NewsdataIO\Constants;
use PHPUnit\Framework\TestCase;

/**
 * A 429 covers three cases: a burst limit, a rate limit, and exhausted API
 * credits. Only the first two are worth retrying — waiting out the backoff
 * cannot conjure more credits.
 *
 * The retry loop itself needs a live socket, so these pin the code set and the
 * classification helper rather than driving cURL.
 */
class QuotaExhaustedTest extends TestCase
{
    public function testQuotaCodesMatchTheApi(): void
    {
        // `ApiLimitExceeded` is in the spec's ErrorCode enum; the key-scoped
        // variant is accepted too because the spec is not exhaustive.
        $this->assertContains('ApiLimitExceeded', Constants::QUOTA_EXHAUSTED_CODES);
        $this->assertContains('ApiKeyLimitExceeded', Constants::QUOTA_EXHAUSTED_CODES);
        $this->assertCount(2, Constants::QUOTA_EXHAUSTED_CODES);
    }

    public function testTransientRateLimitCodesAreNotTreatedAsQuota(): void
    {
        // These are retryable and must stay out of the set.
        foreach (['RateLimitExceeded', 'TooManyRequests'] as $code) {
            $this->assertNotContains($code, Constants::QUOTA_EXHAUSTED_CODES);
        }
    }

    /**
     * The classification helper is private; exercise it through reflection so
     * the body-shape handling (array and object decoding) is covered.
     *
     * @dataProvider quotaBodies
     *
     * @param mixed $body
     */
    public function testQuotaExhaustedDetection($body, bool $expected): void
    {
        $api = new \NewsdataIO\NewsdataApi('key');
        $method = new \ReflectionMethod($api, 'quotaExhausted');
        $method->setAccessible(true);

        $this->assertSame($expected, $method->invoke($api, $body));
    }

    /** @return array<string,array{0:mixed,1:bool}> */
    public static function quotaBodies(): array
    {
        return [
            'array, quota code' => [
                ['status' => 'error', 'results' => ['code' => 'ApiLimitExceeded']],
                true,
            ],
            'array, key quota code' => [
                ['status' => 'error', 'results' => ['code' => 'ApiKeyLimitExceeded']],
                true,
            ],
            'array, transient code' => [
                ['status' => 'error', 'results' => ['code' => 'RateLimitExceeded']],
                false,
            ],
            'object, quota code' => [
                (object) ['status' => 'error', 'results' => (object) ['code' => 'ApiLimitExceeded']],
                true,
            ],
            'object, transient code' => [
                (object) ['status' => 'error', 'results' => (object) ['code' => 'TooManyRequests']],
                false,
            ],
            'no code at all' => [['status' => 'error', 'results' => []], false],
            'no results key' => [['status' => 'error'], false],
            'not a body' => ['plain string', false],
        ];
    }
}
