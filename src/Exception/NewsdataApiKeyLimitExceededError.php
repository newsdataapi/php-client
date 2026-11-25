<?php

declare(strict_types=1);

namespace NewsdataIO\Exception;

/**
 * Raised on 429 when this specific API key's own credit cap is exhausted,
 * even though the account's overall pool may still have credits left.
 *
 * Never retried — waiting out the backoff cannot conjure more credits.
 */
class NewsdataApiKeyLimitExceededError extends NewsdataRateLimitError
{
}
