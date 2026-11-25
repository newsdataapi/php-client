<?php

declare(strict_types=1);

namespace NewsdataIO\Exception;

/**
 * Raised on 429 when the account's pool of API credits is exhausted, rather
 * than a transient rate limit having been hit.
 *
 * Never retried — waiting out the backoff cannot conjure more credits.
 */
class NewsdataApiLimitExceededError extends NewsdataRateLimitError
{
}
