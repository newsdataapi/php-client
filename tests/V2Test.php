<?php

declare(strict_types=1);

namespace NewsdataIO\Tests;

use NewsdataIO\Constants;
use NewsdataIO\Exception\NewsdataValidationError;
use NewsdataIO\NewsdataApi;
use NewsdataIO\ParamValidator;
use PHPUnit\Framework\TestCase;

/**
 * The v2 endpoints: versioned paths, the snake_case filter vocabulary, and the
 * filters v2 dropped. No network access — the validator and the endpoint maps
 * are what these pin down.
 */
final class V2Test extends TestCase
{
    /** @return array<string,array{0:string,1:string}> */
    public static function v2Endpoints(): array
    {
        return [
            'news' => ['news_v2', '2/news'],
            'market' => ['market_v2', '2/market'],
            'crypto' => ['crypto_v2', '2/crypto'],
            'sources' => ['sources_v2', '2/sources'],
            'count news' => ['count_news_v2', '2/count/news'],
            'count market' => ['count_market_v2', '2/count/market'],
            'count crypto' => ['count_crypto_v2', '2/count/crypto'],
        ];
    }

    /**
     * @dataProvider v2Endpoints
     */
    public function testEachV2EndpointHasAVersionedPathAndFilterSet(
        string $key,
        string $path
    ): void {
        $this->assertSame($path, Constants::ENDPOINTS[$key]);
        $this->assertArrayHasKey($key, Constants::FILTERS);
        $this->assertNotEmpty(Constants::FILTERS[$key]);
    }

    public function testBaseUrlNoLongerCarriesTheVersion(): void
    {
        // The version lives in the endpoint path so v1 and v2 can share a base.
        $this->assertSame('https://newsdata.io/api/', Constants::BASE_URL);
        $this->assertStringStartsWith('1/', Constants::ENDPOINTS['latest']);
        $this->assertStringStartsWith('2/', Constants::ENDPOINTS['news_v2']);
    }

    public function testEveryV2MethodIsExposed(): void
    {
        $api = new NewsdataApi('key');
        foreach ([
            'get_news', 'get_market', 'get_crypto', 'get_sources',
            'get_count_news', 'get_count_market', 'get_count_crypto',
        ] as $method) {
            $this->assertTrue(method_exists($api, $method), $method . ' should exist');
        }
    }

    public function testV2AcceptsTheSnakeCaseVocabulary(): void
    {
        $out = ParamValidator::validate('news_v2', [
            'q_in_title' => 'tesla',
            'exclude_country' => 'cn',
            'source_url' => 'bbc.com',
            'source_priority' => 'top',
            'content' => true,
            'remove_duplicate' => true,
            'exclude_field' => 'description',
        ]);

        $this->assertSame([
            'q_in_title' => 'tesla',
            'exclude_country' => 'cn',
            'source_url' => 'bbc.com',
            'source_priority' => 'top',
            'content' => '1',
            'remove_duplicate' => '1',
            'exclude_field' => 'description',
        ], $out);
    }

    /** @return array<string,array{0:array<string,mixed>}> */
    public static function v1Spellings(): array
    {
        return [
            'qInTitle' => [['qInTitle' => 'x']],
            'domain' => [['domain' => 'bbc.com']],
            'domainurl' => [['domainurl' => 'bbc.com']],
            'excludedomain' => [['excludedomain' => 'bbc.com']],
            'excludedomainurl' => [['excludedomainurl' => 'bbc.com']],
            'prioritydomain' => [['prioritydomain' => 'top']],
            'full_content' => [['full_content' => true]],
            'removeduplicate' => [['removeduplicate' => true]],
            'excludefield' => [['excludefield' => 'description']],
        ];
    }

    /**
     * @dataProvider v1Spellings
     *
     * @param array<string,mixed> $params
     */
    public function testV2RejectsTheV1SpellingsItReplaced(array $params): void
    {
        $this->expectException(NewsdataValidationError::class);
        ParamValidator::validate('news_v2', $params);
    }

    public function testV1StillAcceptsTheV1Spellings(): void
    {
        $out = ParamValidator::validate('latest', [
            'qInTitle' => 'x',
            'domain' => 'bbc.com',
            'full_content' => true,
        ]);
        $this->assertSame(
            ['qintitle' => 'x', 'domain' => 'bbc.com', 'full_content' => '1'],
            $out
        );
    }

    public function testSourceUrlAndExcludeSourceUrlAreMutuallyExclusive(): void
    {
        $this->expectException(NewsdataValidationError::class);
        ParamValidator::validate('news_v2', [
            'source_url' => 'a.com',
            'exclude_source_url' => 'b.com',
        ]);
    }

    public function testV2QGroupIsMutuallyExclusive(): void
    {
        $this->expectException(NewsdataValidationError::class);
        ParamValidator::validate('news_v2', ['q' => 'a', 'q_in_title' => 'b']);
    }

    public function testV2CountryGroupIsMutuallyExclusive(): void
    {
        $this->expectException(NewsdataValidationError::class);
        ParamValidator::validate('news_v2', ['country' => 'us', 'exclude_country' => 'cn']);
    }

    public function testV2RawQueryIsCheckedAgainstTheV2Keyset(): void
    {
        $out = ParamValidator::validate('news_v2', [
            'raw_query' => 'q_in_title=tesla&source_url=bbc.com',
        ]);
        $this->assertSame(
            ['q_in_title' => 'tesla', 'source_url' => 'bbc.com'],
            $out
        );
    }

    public function testV2RawQueryRejectsAV1Name(): void
    {
        $this->expectException(NewsdataValidationError::class);
        ParamValidator::validate('news_v2', ['raw_query' => 'qInTitle=tesla']);
    }

    public function testCoinAndMarketIdKeepTheirNames(): void
    {
        $this->assertContains('coin', Constants::FILTERS['crypto_v2']);
        $this->assertContains('coin', Constants::FILTERS['count_crypto_v2']);
        $this->assertContains('market_id', Constants::FILTERS['market_v2']);
        $this->assertContains('market_id', Constants::FILTERS['count_market_v2']);
    }

    public function testV2SourcesAcceptsRawQuery(): void
    {
        // Unlike v1 sources, the v2 one takes a raw query too.
        $out = ParamValidator::validate('sources_v2', ['raw_query' => 'country=us']);
        $this->assertSame(['country' => 'us'], $out);
    }

    // ---- excludedomainurl (v1) -------------------------------------------

    /** @return array<string,array{0:string}> */
    public static function v1EndpointsTakingExcludeDomainUrl(): array
    {
        return [
            'latest' => ['latest'],
            'archive' => ['archive'],
            'crypto' => ['crypto'],
            'market' => ['market'],
            'count' => ['count'],
            'crypto_count' => ['crypto_count'],
            'market_count' => ['market_count'],
        ];
    }

    /**
     * @dataProvider v1EndpointsTakingExcludeDomainUrl
     */
    public function testExcludeDomainUrlIsAcceptedOnTheV1Endpoints(string $endpoint): void
    {
        $this->assertContains('excludedomainurl', Constants::FILTERS[$endpoint]);
    }

    public function testExcludeDomainUrlIsNotASourcesFilter(): void
    {
        $this->assertNotContains('excludedomainurl', Constants::FILTERS['sources']);
    }

    public function testExcludeDomainUrlJoinsTheV1DomainMutexGroup(): void
    {
        $this->expectException(NewsdataValidationError::class);
        ParamValidator::validate('latest', [
            'domain' => 'a.com',
            'excludedomainurl' => 'b.com',
        ]);
    }
}
