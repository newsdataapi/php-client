<?php

declare(strict_types=1);

namespace NewsdataIO\Exception;

/**
 * A network-level failure (cURL error, DNS, TLS, timeout) prevented the
 * request from completing. The underlying error is available via
 * {@see getCode()} / {@see getPrevious()}.
 */
class NewsdataNetworkError extends NewsdataException
{
    /**
     * @param string          $message
     * @param int             $code      cURL error number, when available.
     * @param \Throwable|null $previous
     */
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
