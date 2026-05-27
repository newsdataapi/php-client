<?php

declare(strict_types=1);

namespace NewsdataIO;

/**
 * Static configuration for the Newsdata.io client: base URL, endpoint paths,
 * HTTP defaults, and the per-endpoint accepted-parameter sets.
 *
 * The parameter sets mirror the server-side filter mapping and the official
 * Python client surface. All parameter names are lowercase here; user-supplied
 * keys are lowercased before validation (the API itself is case-insensitive,
 * so `qInTitle` and `qintitle` are equivalent).
 */
final class Constants
{
    /** Newsdata.io API host. */
    public const API_HOST = 'https://newsdata.io/';

    /** API base path. */
    public const API_BASE_PATH = 'api/';

    /** API version. */
    public const API_VERSION = '1';

    /** Fully-qualified base URL, with a trailing slash. */
    public const BASE_URL = self::API_HOST . self::API_BASE_PATH . self::API_VERSION . '/';

    // ---- HTTP defaults ----------------------------------------------------

    /** Seconds to wait for a complete response. */
    public const DEFAULT_REQUEST_TIMEOUT = 30;

    /** Seconds to wait while connecting. */
    public const DEFAULT_CONNECT_TIMEOUT = 10;

    /** Total request attempts (1 = no retry). */
    public const DEFAULT_MAX_RETRIES = 5;

    /** Base seconds for exponential backoff; doubles each attempt. */
    public const DEFAULT_RETRY_BACKOFF = 2.0;

    /** Cap on any single backoff sleep, in seconds. */
    public const DEFAULT_RETRY_BACKOFF_MAX = 60.0;

    /** Allowed response size bounds (a single account cannot exceed 50). */
    public const SIZE_MIN = 1;
    public const SIZE_MAX = 50;

    /**
     * Endpoint key => path appended to {@see BASE_URL}.
     */
    public const ENDPOINTS = [
        'latest'       => 'latest',
        'crypto'       => 'crypto',
        'archive'      => 'archive',
        'sources'      => 'sources',
        'market'       => 'market',
        'count'        => 'count',
        'crypto_count' => 'crypto/count',
        'market_count' => 'market/count',
    ];

    /** Endpoints that require both `from_date` and `to_date`. */
    public const REQUIRES_DATE_RANGE = ['count', 'crypto_count', 'market_count'];

    /** Parameters sent as boolean flags (coerced to `1` / `0`). */
    public const BOOL_PARAMS = ['full_content', 'image', 'video', 'removeduplicate'];

    /** Parameters that must be integers. */
    public const INT_PARAMS = ['size'];

    /** Parameters that must be numeric (int or float). */
    public const FLOAT_PARAMS = ['sentiment_score'];

    /**
     * Mutually-exclusive parameter groups. Setting more than one member of a
     * group raises a validation error before the request is sent.
     */
    public const MUTEX_GROUPS = [
        ['q', 'qintitle', 'qinmeta'],
        ['country', 'excludecountry'],
        ['category', 'excludecategory'],
        ['language', 'excludelanguage'],
        ['domain', 'domainurl', 'excludedomain'],
    ];

    /**
     * Per-endpoint accepted parameters (lowercase).
     */
    public const FILTERS = [
        'latest' => [
            'q', 'qintitle', 'qinmeta', 'country', 'excludecountry', 'category',
            'excludecategory', 'language', 'excludelanguage', 'domain', 'domainurl',
            'excludedomain', 'prioritydomain', 'timeframe', 'timezone', 'size',
            'full_content', 'image', 'video', 'page', 'tag', 'sentiment', 'region',
            'excludefield', 'removeduplicate', 'id', 'organization', 'url', 'sort',
            'creator', 'datatype', 'sentiment_score',
        ],
        'archive' => [
            'q', 'qintitle', 'qinmeta', 'country', 'excludecountry', 'category',
            'excludecategory', 'language', 'excludelanguage', 'domain', 'domainurl',
            'excludedomain', 'prioritydomain', 'timezone', 'size', 'full_content',
            'image', 'video', 'page', 'from_date', 'to_date', 'excludefield', 'id',
            'url', 'sort', 'tag', 'sentiment', 'sentiment_score', 'region',
            'organization', 'creator', 'datatype', 'removeduplicate',
        ],
        'crypto' => [
            'q', 'qintitle', 'qinmeta', 'language', 'excludelanguage', 'domain',
            'domainurl', 'excludedomain', 'prioritydomain', 'timeframe', 'timezone',
            'size', 'full_content', 'image', 'video', 'page', 'tag', 'sentiment',
            'coin', 'excludefield', 'from_date', 'to_date', 'removeduplicate', 'id',
            'url', 'sort',
        ],
        'sources' => [
            'country', 'category', 'language', 'prioritydomain', 'domainurl',
        ],
        'market' => [
            'q', 'qintitle', 'qinmeta', 'from_date', 'to_date', 'country',
            'excludecountry', 'domain', 'domainurl', 'excludedomain', 'language',
            'excludelanguage', 'prioritydomain', 'timezone', 'timeframe', 'size',
            'full_content', 'image', 'video', 'page', 'tag', 'sentiment',
            'excludefield', 'removeduplicate', 'organization', 'symbol', 'id', 'url',
            'sort', 'creator', 'datatype', 'sentiment_score',
        ],
        'count' => [
            'from_date', 'to_date', 'q', 'qintitle', 'qinmeta', 'country',
            'excludecountry', 'category', 'excludecategory', 'language',
            'excludelanguage', 'domain', 'domainurl', 'excludedomain', 'full_content',
            'image', 'video', 'prioritydomain', 'page', 'size', 'sort', 'interval',
            'tag', 'sentiment', 'sentiment_score', 'region', 'organization', 'creator',
            'datatype', 'removeduplicate',
        ],
        'crypto_count' => [
            'from_date', 'to_date', 'q', 'qintitle', 'qinmeta', 'language',
            'excludelanguage', 'coin', 'domain', 'domainurl', 'excludedomain',
            'full_content', 'image', 'video', 'prioritydomain', 'page', 'sentiment',
            'size', 'sort', 'tag', 'interval', 'removeduplicate',
        ],
        'market_count' => [
            'from_date', 'to_date', 'q', 'qintitle', 'qinmeta', 'country',
            'excludecountry', 'domain', 'domainurl', 'excludedomain', 'language',
            'excludelanguage', 'full_content', 'image', 'video', 'organization',
            'symbol', 'prioritydomain', 'page', 'sentiment', 'removeduplicate', 'size',
            'sort', 'tag', 'interval', 'creator', 'datatype', 'sentiment_score',
        ],
    ];
}
