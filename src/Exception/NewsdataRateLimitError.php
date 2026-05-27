<?php

declare(strict_types=1);

namespace NewsdataIO\Exception;

/**
 * Raised on 429 responses once retries are exhausted.
 *
 * {@see getRetryAfter()} exposes the seconds to wait before retrying, parsed
 * from the ``Retry-After`` header when the server provided a usable value.
 */
class NewsdataRateLimitError extends NewsdataAPIError
{
    /** @var int|null Seconds to wait before retrying, when known. */
    private $retryAfter;

    /**
     * @param string     $message
     * @param int|null   $statusCode
     * @param array|null $responseBody
     * @param int|null   $retryAfter
     */
    public function __construct(
        string $message,
        ?int $statusCode = 429,
        ?array $responseBody = null,
        ?int $retryAfter = null
    ) {
        parent::__construct($message, $statusCode, $responseBody);
        $this->retryAfter = $retryAfter;
    }

    /**
     * @return int|null
     */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
