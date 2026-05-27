<?php

declare(strict_types=1);

namespace NewsdataIO\Exception;

/**
 * The API returned a structured error response.
 *
 * Use {@see getStatusCode()} and {@see getResponseBody()} to inspect the
 * failure.
 */
class NewsdataAPIError extends NewsdataException
{
    /** @var int|null HTTP status returned by the API. */
    private $statusCode;

    /** @var array|null Parsed JSON body of the error response, when available. */
    private $responseBody;

    /**
     * @param string     $message
     * @param int|null   $statusCode
     * @param array|null $responseBody
     */
    public function __construct(string $message, ?int $statusCode = null, ?array $responseBody = null)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->responseBody = $responseBody;
    }

    /**
     * @return int|null
     */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    /**
     * @return array|null
     */
    public function getResponseBody(): ?array
    {
        return $this->responseBody;
    }
}
