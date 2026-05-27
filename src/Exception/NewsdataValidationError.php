<?php

declare(strict_types=1);

namespace NewsdataIO\Exception;

/**
 * A user-provided parameter failed client-side validation.
 *
 * Raised before any request leaves the process, so no API quota is spent.
 */
class NewsdataValidationError extends NewsdataException
{
    /** @var string|null The offending parameter name, when known. */
    private $param;

    /**
     * @param string      $message
     * @param string|null $param
     */
    public function __construct(string $message, ?string $param = null)
    {
        parent::__construct($message);
        $this->param = $param;
    }

    /**
     * @return string|null
     */
    public function getParam(): ?string
    {
        return $this->param;
    }
}
