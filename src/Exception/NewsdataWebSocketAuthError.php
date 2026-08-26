<?php

declare(strict_types=1);

namespace NewsdataIO\Exception;

/**
 * The server rejected the WebSocket connection — bad API key, missing
 * WebSocket entitlement, unknown `registration_id`, device limit reached, or
 * exhausted quota. Never retried, regardless of the `reconnect` setting.
 */
class NewsdataWebSocketAuthError extends NewsdataWebSocketError
{
}
