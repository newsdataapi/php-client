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

    /**
     * API version of the v1 endpoints.
     *
     * @deprecated The version now lives in the endpoint path, so that v1 and
     *             v2 endpoints can share one base URL. See {@see ENDPOINTS}.
     */
    public const API_VERSION = '1';

    /**
     * Fully-qualified API root, with a trailing slash. The endpoint paths in
     * {@see ENDPOINTS} carry the API version.
     */
    public const BASE_URL = self::API_HOST . self::API_BASE_PATH;

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
        // v1
        'latest'       => '1/latest',
        'crypto'       => '1/crypto',
        'archive'      => '1/archive',
        'sources'      => '1/sources',
        'market'       => '1/market',
        'count'        => '1/count',
        'crypto_count' => '1/crypto/count',
        'market_count' => '1/market/count',
        // v2
        'news_v2'         => '2/news',
        'market_v2'       => '2/market',
        'crypto_v2'       => '2/crypto',
        'sources_v2'      => '2/sources',
        'count_news_v2'   => '2/count/news',
        'count_market_v2' => '2/count/market',
        'count_crypto_v2' => '2/count/crypto',
        // The real-time service is v2 only.
        'websocket_register' => '2/websocket/register',
        'websocket_fetch'    => '2/websocket/fetch',
        'websocket_delete'   => '2/websocket/delete',
    ];

    /** HTTP method per endpoint; anything absent is a GET. */
    public const ENDPOINT_METHODS = [
        'websocket_register' => 'POST',
        'websocket_delete'   => 'DELETE',
    ];

    /**
     * Endpoints whose success envelope may carry no `results` field, so they
     * are exempt from the results-present check applied to the news endpoints.
     */
    public const RESULTS_OPTIONAL = [
        'websocket_register',
        'websocket_fetch',
        'websocket_delete',
    ];

    /** Real-time WebSocket endpoint. */
    public const WS_BASE_URL = 'wss://ws.newsdata.io/ws/event';

    /** The feed a registered query matches against; only news so far. */
    public const WS_NEWS_TYPE = 'news';

    /** Close code the server uses for a permanent connection rejection. */
    public const WS_POLICY_VIOLATION = 1008;

    /** Seconds before the first reconnect; doubles after each failure. */
    public const WS_RECONNECT_DELAY = 1.0;

    /** Upper bound on the reconnect delay, in seconds. */
    public const WS_RECONNECT_DELAY_MAX = 30.0;

    /** Bound on the opening handshake, in seconds. */
    public const WS_HANDSHAKE_TIMEOUT = 10;

    /**
     * Error codes on a 429 that waiting cannot fix, so they are never retried.
     * `ApiLimitExceeded` and `ApiKeyLimitExceeded` mean the API credits are
     * exhausted rather than a transient rate limit — backoff cannot conjure
     * more credits. `RegistrationLimitExceeded` means every real-time
     * registration the plan allows is in use; only deleting one frees a slot.
     *
     * `ApiLimitExceeded` is the documented code (see the ErrorCode enum in
     * https://newsdata.io/openapi.json); `ApiKeyLimitExceeded` is accepted too
     * because the API has been observed to send it and the spec is not
     * exhaustive.
     */
    public const QUOTA_EXHAUSTED_CODES = [
        'ApiLimitExceeded',
        'ApiKeyLimitExceeded',
        'RegistrationLimitExceeded',
    ];

    /**
     * Error code on a 429 => the exception class to throw. None of them is
     * worth retrying, but the remedy differs: `ApiLimitExceeded` is the
     * account's whole credit pool, `ApiKeyLimitExceeded` is this one key's own
     * cap, and `RegistrationLimitExceeded` is the real-time registration slots
     * — it keeps the base class, since it is not about credits at all.
     */
    public const QUOTA_EXHAUSTED_ERRORS = [
        'ApiLimitExceeded'          => Exception\NewsdataApiLimitExceededError::class,
        'ApiKeyLimitExceeded'       => Exception\NewsdataApiKeyLimitExceededError::class,
        'RegistrationLimitExceeded' => Exception\NewsdataRateLimitError::class,
    ];

    /** Endpoints that require both `from_date` and `to_date`. */
    public const REQUIRES_DATE_RANGE = ['count', 'crypto_count', 'market_count'];

    /** Parameters sent as boolean flags (coerced to `1` / `0`). */
    public const BOOL_PARAMS = [
        'full_content', 'image', 'video', 'removeduplicate',
        // v2 spellings
        'content', 'remove_duplicate',
    ];

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
        ['domain', 'domainurl', 'excludedomain', 'excludedomainurl'],
        // v2 names
        ['q', 'q_in_title', 'q_in_meta'],
        ['country', 'exclude_country'],
        ['category', 'exclude_category'],
        ['language', 'exclude_language'],
        ['source_url', 'exclude_source_url'],
    ];

    /**
     * Per-endpoint accepted parameters (lowercase).
     */
    public const FILTERS = [
        'latest' => [
            'q', 'qintitle', 'qinmeta', 'country', 'excludecountry', 'category',
            'excludecategory', 'language', 'excludelanguage', 'domain',
            'domainurl', 'excludedomain', 'excludedomainurl', 'prioritydomain',
            'timeframe', 'timezone', 'size', 'full_content', 'image', 'video',
            'page', 'tag', 'sentiment', 'region', 'excludefield',
            'removeduplicate', 'id', 'organization', 'url', 'sort', 'creator',
            'datatype', 'sentiment_score',
        ],
        'archive' => [
            'q', 'qintitle', 'qinmeta', 'country', 'excludecountry', 'category',
            'excludecategory', 'language', 'excludelanguage', 'domain',
            'domainurl', 'excludedomain', 'excludedomainurl', 'prioritydomain',
            'timezone', 'size', 'full_content', 'image', 'video', 'page',
            'from_date', 'to_date', 'excludefield', 'id', 'url', 'sort', 'tag',
            'sentiment', 'sentiment_score', 'region', 'organization', 'creator',
            'datatype', 'removeduplicate',
        ],
        'crypto' => [
            'q', 'qintitle', 'qinmeta', 'language', 'excludelanguage', 'domain',
            'domainurl', 'excludedomain', 'excludedomainurl', 'prioritydomain',
            'timeframe', 'timezone', 'size', 'full_content', 'image', 'video',
            'page', 'tag', 'sentiment', 'coin', 'excludefield', 'from_date',
            'to_date', 'removeduplicate', 'id', 'url', 'sort',
        ],
        'sources' => [
            'country', 'category', 'language', 'prioritydomain', 'domainurl',
        ],
        'market' => [
            'q', 'qintitle', 'qinmeta', 'from_date', 'to_date', 'country',
            'excludecountry', 'domain', 'domainurl', 'excludedomain',
            'excludedomainurl', 'language', 'excludelanguage', 'prioritydomain',
            'timezone', 'timeframe', 'size', 'full_content', 'image', 'video',
            'page', 'tag', 'sentiment', 'excludefield', 'removeduplicate',
            'organization', 'market_id', 'id', 'url', 'sort', 'creator',
            'datatype', 'sentiment_score',
        ],
        'count' => [
            'from_date', 'to_date', 'q', 'qintitle', 'qinmeta', 'country',
            'excludecountry', 'category', 'excludecategory', 'language',
            'excludelanguage', 'domain', 'domainurl', 'excludedomain',
            'excludedomainurl', 'full_content', 'image', 'video', 'prioritydomain',
            'page', 'size', 'sort', 'interval', 'tag', 'sentiment',
            'sentiment_score', 'region', 'organization', 'creator', 'datatype',
            'removeduplicate',
        ],
        'crypto_count' => [
            'from_date', 'to_date', 'q', 'qintitle', 'qinmeta', 'language',
            'excludelanguage', 'coin', 'domain', 'domainurl', 'excludedomain',
            'excludedomainurl', 'full_content', 'image', 'video', 'prioritydomain',
            'page', 'sentiment', 'size', 'sort', 'tag', 'interval',
            'removeduplicate',
        ],
        'market_count' => [
            'from_date', 'to_date', 'q', 'qintitle', 'qinmeta', 'country',
            'excludecountry', 'domain', 'domainurl', 'excludedomain',
            'excludedomainurl', 'language', 'excludelanguage', 'full_content',
            'image', 'video', 'organization', 'market_id', 'prioritydomain',
            'page', 'sentiment', 'removeduplicate', 'size', 'sort', 'tag',
            'interval', 'creator', 'datatype', 'sentiment_score',
        ],

        // ---- v2 ----------------------------------------------------------
        // v2 renames the filter vocabulary to snake_case and folds
        // domain/domainurl/excludedomain/excludedomainurl into
        // source_url/exclude_source_url.
        'news_v2' => [
            'q', 'q_in_title', 'q_in_meta', 'country', 'exclude_country',
            'category', 'exclude_category', 'language', 'exclude_language',
            'source_url', 'exclude_source_url', 'source_priority', 'from_date',
            'to_date', 'timeframe', 'timezone', 'size', 'content', 'image',
            'video', 'page', 'tag', 'sentiment', 'sentiment_score', 'region',
            'organization', 'creator', 'datatype', 'exclude_field',
            'remove_duplicate', 'id', 'url', 'sort',
        ],
        'market_v2' => [
            'q', 'q_in_title', 'q_in_meta', 'country', 'exclude_country',
            'language', 'exclude_language', 'source_url', 'exclude_source_url',
            'source_priority', 'from_date', 'to_date', 'timeframe', 'timezone',
            'size', 'content', 'image', 'video', 'page', 'tag', 'sentiment',
            'sentiment_score', 'organization', 'market_id', 'creator', 'datatype',
            'exclude_field', 'remove_duplicate', 'id', 'url', 'sort',
        ],
        'crypto_v2' => [
            'q', 'q_in_title', 'q_in_meta', 'coin', 'language', 'exclude_language',
            'source_url', 'exclude_source_url', 'source_priority', 'from_date',
            'to_date', 'timeframe', 'timezone', 'size', 'content', 'image',
            'video', 'page', 'tag', 'sentiment', 'exclude_field',
            'remove_duplicate', 'id', 'url', 'sort',
        ],
        'sources_v2' => [
            'country', 'category', 'language', 'source_priority', 'source_url',
        ],
        'count_news_v2' => [
            'q', 'q_in_title', 'q_in_meta', 'country', 'exclude_country',
            'category', 'exclude_category', 'language', 'exclude_language',
            'source_url', 'exclude_source_url', 'source_priority', 'from_date',
            'to_date', 'timeframe', 'interval', 'size', 'content', 'image',
            'video', 'page', 'tag', 'sentiment', 'sentiment_score', 'region',
            'organization', 'creator', 'datatype', 'remove_duplicate', 'sort',
        ],
        'count_market_v2' => [
            'q', 'q_in_title', 'q_in_meta', 'country', 'exclude_country',
            'language', 'exclude_language', 'source_url', 'exclude_source_url',
            'source_priority', 'from_date', 'to_date', 'timeframe', 'interval',
            'size', 'content', 'image', 'video', 'page', 'tag', 'sentiment',
            'sentiment_score', 'organization', 'market_id', 'creator', 'datatype',
            'remove_duplicate', 'sort',
        ],
        'count_crypto_v2' => [
            'q', 'q_in_title', 'q_in_meta', 'coin', 'language', 'exclude_language',
            'source_url', 'exclude_source_url', 'source_priority', 'from_date',
            'to_date', 'timeframe', 'interval', 'size', 'content', 'image',
            'video', 'page', 'tag', 'sentiment', 'remove_duplicate', 'sort',
        ],
        // Real-time query registration. The real-time service is v2 only, so
        // these are the v2 filter names. No date or paging filters apply — a
        // registered query matches news as it is published. `news_type` is set
        // by get_websocket_register(), not by the caller.
        'websocket_register' => [
            'q', 'q_in_title', 'q_in_meta', 'country', 'exclude_country',
            'category', 'exclude_category', 'language', 'exclude_language',
            'source_url', 'exclude_source_url', 'source_priority', 'timezone',
            'content', 'image', 'video', 'remove_duplicate', 'tag', 'sentiment',
            'sentiment_score', 'region', 'organization', 'creator', 'datatype',
            'exclude_field', 'news_type',
        ],
        'websocket_fetch'  => [],
        'websocket_delete' => ['registration_id'],
    ];
}
