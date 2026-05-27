<?php

declare(strict_types=1);

namespace NewsdataIO;

use NewsdataIO\Exception\NewsdataValidationError;

/**
 * Validates and normalizes a user-supplied parameter array for a given
 * endpoint, mirroring the client-side checks performed by the official
 * Python client:
 *
 *  - keys are lowercased (the API is case-insensitive);
 *  - `null` values are dropped;
 *  - arrays are comma-joined; booleans become `1` / `0`;
 *  - `size` must be an int within the allowed bounds;
 *  - `sentiment_score` must be numeric and requires `sentiment`;
 *  - mutually-exclusive groups are rejected;
 *  - unknown parameters for the endpoint are rejected;
 *  - `raw_query`, when present, must be the only parameter and is parsed and
 *    checked against the endpoint's allowed keys.
 *
 * The returned array maps parameter name to a string value, ready to be
 * url-encoded into the query string.
 */
final class ParamValidator
{
    /**
     * @param string $endpoint One of the keys in {@see Constants::ENDPOINTS}.
     * @param array  $data      Raw user parameters.
     *
     * @return array<string,string>
     *
     * @throws NewsdataValidationError
     */
    public static function validate(string $endpoint, array $data): array
    {
        if (!isset(Constants::FILTERS[$endpoint])) {
            throw new NewsdataValidationError("Unknown endpoint: {$endpoint}");
        }
        $allowed = Constants::FILTERS[$endpoint];

        // Lowercase keys; the API is case-insensitive and our maps are lower.
        $params = [];
        foreach ($data as $key => $value) {
            $params[strtolower((string) $key)] = $value;
        }

        // raw_query is mutually exclusive with every other parameter.
        if (isset($params['raw_query']) && $params['raw_query'] !== null) {
            $conflicting = [];
            foreach ($params as $key => $value) {
                if ($key !== 'raw_query' && $value !== null) {
                    $conflicting[] = $key;
                }
            }
            if (!empty($conflicting)) {
                sort($conflicting);
                throw new NewsdataValidationError(
                    'raw_query cannot be combined with other parameters; got '
                    . 'raw_query and [' . implode(', ', $conflicting) . ']',
                    'raw_query'
                );
            }
            return self::parseRawQuery($params['raw_query'], $allowed);
        }

        // Count endpoints require an explicit date range.
        if (in_array($endpoint, Constants::REQUIRES_DATE_RANGE, true)) {
            foreach (['from_date', 'to_date'] as $required) {
                if (!isset($params[$required]) || $params[$required] === null || $params[$required] === '') {
                    throw new NewsdataValidationError(
                        "{$required} is required for the {$endpoint} endpoint",
                        $required
                    );
                }
            }
        }

        self::checkMutex($params);

        if (
            isset($params['sentiment_score']) && $params['sentiment_score'] !== null
            && (!isset($params['sentiment']) || $params['sentiment'] === null)
        ) {
            throw new NewsdataValidationError(
                'sentiment_score requires sentiment to be set',
                'sentiment_score'
            );
        }

        $validated = [];
        foreach ($params as $param => $value) {
            if ($value === null || $param === 'raw_query') {
                continue;
            }
            if (!in_array($param, $allowed, true)) {
                throw new NewsdataValidationError(
                    "Unsupported parameter for the {$endpoint} endpoint: {$param}",
                    $param
                );
            }
            $validated[$param] = self::coerce($param, $value);
        }

        return $validated;
    }

    /**
     * @param array $params Lowercased parameter map.
     *
     * @throws NewsdataValidationError
     */
    private static function checkMutex(array $params): void
    {
        foreach (Constants::MUTEX_GROUPS as $group) {
            $set = [];
            foreach ($group as $name) {
                if (isset($params[$name]) && $params[$name] !== null) {
                    $set[] = $name;
                }
            }
            if (count($set) > 1) {
                throw new NewsdataValidationError(
                    'these parameters are mutually exclusive: [' . implode(', ', $set) . ']',
                    $set[0]
                );
            }
        }
    }

    /**
     * @param string $param
     * @param mixed  $value
     *
     * @return string
     *
     * @throws NewsdataValidationError
     */
    private static function coerce(string $param, $value): string
    {
        if (in_array($param, Constants::BOOL_PARAMS, true)) {
            return self::coerceBool($param, $value);
        }
        if (in_array($param, Constants::INT_PARAMS, true)) {
            return self::coerceInt($param, $value);
        }
        if (in_array($param, Constants::FLOAT_PARAMS, true)) {
            return self::coerceFloat($param, $value);
        }
        return self::coerceString($param, $value);
    }

    /**
     * @param mixed $value
     */
    private static function coerceBool(string $param, $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if ($value === 0 || $value === 1) {
            return (string) $value;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes'], true)) {
                return '1';
            }
            if (in_array($normalized, ['0', 'false', 'no'], true)) {
                return '0';
            }
        }
        throw new NewsdataValidationError(
            "{$param} must be a boolean",
            $param
        );
    }

    /**
     * @param mixed $value
     */
    private static function coerceInt(string $param, $value): string
    {
        if (is_bool($value)) {
            throw new NewsdataValidationError("{$param} must be an integer", $param);
        }
        if (is_int($value)) {
            $int = $value;
        } elseif (is_string($value) && ctype_digit($value)) {
            $int = (int) $value;
        } else {
            throw new NewsdataValidationError("{$param} must be an integer", $param);
        }

        if ($param === 'size' && ($int < Constants::SIZE_MIN || $int > Constants::SIZE_MAX)) {
            throw new NewsdataValidationError(
                'size must be between ' . Constants::SIZE_MIN . ' and ' . Constants::SIZE_MAX
                . " (got {$int})",
                'size'
            );
        }
        return (string) $int;
    }

    /**
     * @param mixed $value
     */
    private static function coerceFloat(string $param, $value): string
    {
        if (is_bool($value) || !is_numeric($value)) {
            throw new NewsdataValidationError("{$param} must be a number", $param);
        }
        return (string) $value;
    }

    /**
     * @param mixed $value
     */
    private static function coerceString(string $param, $value): string
    {
        if (is_array($value)) {
            $items = [];
            foreach ($value as $item) {
                if (is_bool($item) || is_array($item) || $item === null || is_object($item)) {
                    throw new NewsdataValidationError(
                        "all items in {$param} must be strings",
                        $param
                    );
                }
                $items[] = (string) $item;
            }
            return implode(',', $items);
        }
        if (is_bool($value) || is_object($value)) {
            throw new NewsdataValidationError(
                "{$param} must be a string or array of strings",
                $param
            );
        }
        return (string) $value;
    }

    /**
     * Parse a `raw_query` value (a query-string fragment or a full URL) into a
     * validated parameter map.
     *
     * @param mixed $rawQuery
     * @param array $allowed  Allowed parameter names for the endpoint.
     *
     * @return array<string,string>
     *
     * @throws NewsdataValidationError
     */
    private static function parseRawQuery($rawQuery, array $allowed): array
    {
        if (!is_string($rawQuery)) {
            throw new NewsdataValidationError('raw_query must be a string', 'raw_query');
        }
        if ($rawQuery === '') {
            throw new NewsdataValidationError('raw_query must be a non-empty string', 'raw_query');
        }

        $queryString = $rawQuery;
        if (preg_match('#^[a-z][a-z0-9+.\-]*://#i', $rawQuery) === 1) {
            $parts = parse_url($rawQuery);
            $queryString = isset($parts['query']) ? $parts['query'] : '';
        }
        $queryString = ltrim($queryString, '?');

        $parsed = [];
        parse_str($queryString, $parsed);

        $result = [];
        foreach ($parsed as $key => $value) {
            $normalized = strtolower(trim((string) $key));
            if ($normalized === '') {
                continue;
            }
            // apikey comes from the client constructor; ignore any embedded one.
            if ($normalized === 'apikey') {
                continue;
            }
            if (!in_array($normalized, $allowed, true)) {
                throw new NewsdataValidationError(
                    "Unknown parameter in raw_query: {$key}",
                    (string) $key
                );
            }
            if (is_array($value)) {
                $value = implode(',', array_map('strval', $value));
            }
            if ($value === '' || $value === null) {
                throw new NewsdataValidationError(
                    "Parameter {$key} in raw_query must have a value",
                    (string) $key
                );
            }
            $result[$normalized] = (string) $value;
        }
        return $result;
    }
}
