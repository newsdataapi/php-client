<?php

declare(strict_types=1);

namespace NewsdataIO\Tests;

use NewsdataIO\Constants;
use NewsdataIO\Exception\NewsdataValidationError;
use NewsdataIO\ParamValidator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the client-side parameter validator. No network access.
 */
final class ParamValidatorTest extends TestCase
{
    public function testArraysAreCommaJoined(): void
    {
        $out = ParamValidator::validate('latest', ['country' => ['us', 'gb']]);
        $this->assertSame(['country' => 'us,gb'], $out);
    }

    public function testBooleanIsCoercedToFlag(): void
    {
        $out = ParamValidator::validate('latest', ['full_content' => true, 'image' => false]);
        $this->assertSame(['full_content' => '1', 'image' => '0'], $out);
    }

    public function testKeysAreLowercased(): void
    {
        $out = ParamValidator::validate('latest', ['qInTitle' => 'hi']);
        $this->assertSame(['qintitle' => 'hi'], $out);
    }

    public function testNullValuesAreDropped(): void
    {
        $out = ParamValidator::validate('latest', ['q' => 'x', 'country' => null]);
        $this->assertSame(['q' => 'x'], $out);
    }

    public function testSizeUpperBoundRejected(): void
    {
        $this->expectException(NewsdataValidationError::class);
        ParamValidator::validate('latest', ['size' => Constants::SIZE_MAX + 1]);
    }

    public function testSizeWithinBoundsAccepted(): void
    {
        $out = ParamValidator::validate('latest', ['size' => 50]);
        $this->assertSame(['size' => '50'], $out);
    }

    public function testMutuallyExclusiveParamsRejected(): void
    {
        $this->expectException(NewsdataValidationError::class);
        ParamValidator::validate('latest', ['q' => 'a', 'qInTitle' => 'b']);
    }

    public function testUnknownParameterRejected(): void
    {
        $this->expectException(NewsdataValidationError::class);
        ParamValidator::validate('latest', ['nope' => 'x']);
    }

    public function testCryptoRejectsCountryParam(): void
    {
        $this->expectException(NewsdataValidationError::class);
        ParamValidator::validate('crypto', ['country' => 'us']);
    }

    public function testSentimentScoreRequiresSentiment(): void
    {
        $this->expectException(NewsdataValidationError::class);
        ParamValidator::validate('latest', ['sentiment_score' => 0.5]);
    }

    public function testSentimentScoreWithSentimentAccepted(): void
    {
        $out = ParamValidator::validate('latest', ['sentiment' => 'positive', 'sentiment_score' => 0.5]);
        $this->assertSame(['sentiment' => 'positive', 'sentiment_score' => '0.5'], $out);
    }

    public function testCountRequiresDateRange(): void
    {
        $this->expectException(NewsdataValidationError::class);
        ParamValidator::validate('count', ['q' => 'x']);
    }

    public function testCountWithDatesAccepted(): void
    {
        $out = ParamValidator::validate('count', [
            'from_date' => '2024-01-01',
            'to_date'   => '2024-01-02',
        ]);
        $this->assertSame(['from_date' => '2024-01-01', 'to_date' => '2024-01-02'], $out);
    }

    public function testRawQueryParsedAndValidated(): void
    {
        $out = ParamValidator::validate('latest', ['raw_query' => 'q=foo&country=us']);
        $this->assertSame(['q' => 'foo', 'country' => 'us'], $out);
    }

    public function testRawQueryRejectsOtherParams(): void
    {
        $this->expectException(NewsdataValidationError::class);
        ParamValidator::validate('latest', ['raw_query' => 'q=foo', 'country' => 'us']);
    }

    public function testRawQueryRejectsUnknownKey(): void
    {
        $this->expectException(NewsdataValidationError::class);
        ParamValidator::validate('latest', ['raw_query' => 'bogus=1']);
    }

    public function testRawQueryIgnoresEmbeddedApiKey(): void
    {
        $out = ParamValidator::validate('latest', ['raw_query' => 'apikey=secret&q=foo']);
        $this->assertSame(['q' => 'foo'], $out);
    }

    public function testParamErrorExposesParamName(): void
    {
        try {
            ParamValidator::validate('latest', ['size' => 999]);
            $this->fail('expected NewsdataValidationError');
        } catch (NewsdataValidationError $e) {
            $this->assertSame('size', $e->getParam());
        }
    }
}
