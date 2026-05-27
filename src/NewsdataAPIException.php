<?php

declare(strict_types=1);

namespace NewsdataIO;

use NewsdataIO\Exception\NewsdataException;

/**
 * @deprecated Use the typed exceptions in the NewsdataIO\Exception namespace
 *             instead (NewsdataNetworkError, NewsdataAPIError, NewsdataAuthError,
 *             NewsdataRateLimitError, NewsdataServerError, NewsdataValidationError).
 *
 * Retained only so existing `use NewsdataIO\NewsdataAPIException;` statements
 * keep resolving. It is no longer thrown by the client.
 */
class NewsdataAPIException extends NewsdataException
{
}
