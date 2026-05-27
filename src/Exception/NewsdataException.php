<?php

declare(strict_types=1);

namespace NewsdataIO\Exception;

/**
 * Base class for every error raised by the Newsdata.io PHP client.
 *
 * Catch this to handle any SDK failure regardless of cause.
 */
class NewsdataException extends \Exception
{
}
