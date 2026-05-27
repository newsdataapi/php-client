<?php

declare(strict_types=1);

namespace NewsdataIO\Exception;

/**
 * Raised on 401 / 403 responses (missing, invalid, or unauthorized API key).
 */
class NewsdataAuthError extends NewsdataAPIError
{
}
