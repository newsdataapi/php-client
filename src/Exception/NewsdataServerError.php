<?php

declare(strict_types=1);

namespace NewsdataIO\Exception;

/**
 * Raised on 5xx responses once retries are exhausted.
 */
class NewsdataServerError extends NewsdataAPIError
{
}
