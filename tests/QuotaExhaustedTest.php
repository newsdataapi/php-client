<?php

declare(strict_types=1);

namespace NewsdataIO\Tests;

use NewsdataIO\Constants;
use NewsdataIO\Exception\NewsdataApiKeyLimitExceededError;
use NewsdataIO\Exception\NewsdataApiLimitExceededError;
use NewsdataIO\Exception\NewsdataRateLimitError;
use PHPUnit\Framework\TestCase;

/**
 * A 429 covers a burst limit, a rate limit, exhausted API credits, and a full
 * set of real-time registrations. Only the first two are worth retrying —
 * waiting out the backoff cannot conjure credits or free a registration slot.
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
        // Not a credit code: every real-time registration the plan allows is
        // in use, and only deleting one frees a slot.
        $this->assertContains('RegistrationLimitExceeded', Constants::QUOTA_EXHAUSTED_CODES);
        $this->assertCount(3, Constants::QUOTA_EXHAUSTED_CODES);
    }

    public function testTransientRateLimitCodesAreNotTreatedAsQuota(): void
    {
        // These are retryable and must stay out of the set.
        foreach (['RateLimitExceeded', 'TooManyRequests'] as $code) {
            $this->assertNotContains($code, Constants::QUOTA_EXHAUSTED_CODES);
        }
    }

    public function testEachQuotaCodeMapsToItsOwnErrorClass(): void
    {
        // The two credit codes mean the credits are gone, but the remedy
        // differs, so each gets its own class. Both subclass
        // NewsdataRateLimitError, so code written against the old single class
        // keeps working.
        $this->assertSame(
            NewsdataApiLimitExceededError::class,
            Constants::QUOTA_EXHAUSTED_ERRORS['ApiLimitExceeded']
        );
        $this->assertSame(
            NewsdataApiKeyLimitExceededError::class,
            Constants::QUOTA_EXHAUSTED_ERRORS['ApiKeyLimitExceeded']
        );
        $this->assertTrue(
            is_subclass_of(NewsdataApiLimitExceededError::class, NewsdataRateLimitError::class)
        );
        $this->assertTrue(
            is_subclass_of(NewsdataApiKeyLimitExceededError::class, NewsdataRateLimitError::class)
        );
        // Registration slots are not credits, so this one keeps the base class.
        $this->assertSame(
            NewsdataRateLimitError::class,
            Constants::QUOTA_EXHAUSTED_ERRORS['RegistrationLimitExceeded']
        );
        // Every code in the set must map to a class, or the throw would fall
        // back to the base class and lose the distinction.
        foreach (Constants::QUOTA_EXHAUSTED_CODES as $code) {
            $this->assertArrayHasKey($code, Constants::QUOTA_EXHAUSTED_ERRORS);
        }
    }

    /**
     * The classification helper is private; exercise it through reflection so
     * the body-shape handling (array and object decoding) is covered.
     *
     * @dataProvider quotaBodies
     *
     * @param mixed       $body
     * @param string|null $expected
     */
    public function testQuotaExhaustedDetection($body, ?string $expected): void
    {
        $api = new \NewsdataIO\NewsdataApi('key');
        $method = new \ReflectionMethod($api, 'quotaExhaustedError');
        $method->setAccessible(true);

        $this->assertSame($expected, $method->invoke($api, $body));
    }

    /** @return array<string,array{0:mixed,1:string|null}> */
    public static function quotaBodies(): array
    {
        return [
            'array, quota code' => [
                ['status' => 'error', 'results' => ['code' => 'ApiLimitExceeded']],
                NewsdataApiLimitExceededError::class,
            ],
            'array, key quota code' => [
                ['status' => 'error', 'results' => ['code' => 'ApiKeyLimitExceeded']],
                NewsdataApiKeyLimitExceededError::class,
            ],
            'array, transient code' => [
                ['status' => 'error', 'results' => ['code' => 'RateLimitExceeded']],
                null,
            ],
            'object, quota code' => [
                (object) ['status' => 'error', 'results' => (object) ['code' => 'ApiLimitExceeded']],
                NewsdataApiLimitExceededError::class,
            ],
            'object, transient code' => [
                (object) ['status' => 'error', 'results' => (object) ['code' => 'TooManyRequests']],
                null,
            ],
            'no code at all' => [['status' => 'error', 'results' => []], null],
            'no results key' => [['status' => 'error'], null],
            'not a body' => ['plain string', null],
        ];
    }
}
