<?php
/**
 * Global helper functions
 *
 * @copyright (c) 2018, Fairbanks Publishing
 */

if(!function_exists('boolean')) {
    /**
     * Convert value to boolean
     *
     * Extends boolval() to look at more words as being TRUE.
     * Strings and numbers default to FALSE.
     * Notable differences from boolval() docs:
     *  +----------+-----------+-----------+
     *  | val      | boolval() | boolean() |
     *  +----------+-----------+-----------+
     *  | 0        | false     | false     |
     *  | 42       | true      | false     |
     *  | 0        | false     | false     |
     *  | 4.2      | true      | false     |
     *  | 1        | true      | true      |
     *  | NULL     | false     | false     |
     *  | ""       | false     | false     |
     *  | "string" | true      | false     |
     *  | "0"      | false     | false     |
     *  | "1"      | true      | true      |
     *  | "yes"    | true      | true      |
     *  | "no"     | true      | false     |
     *  | "y"      | true      | true      |
     *  | "n"      | true      | false     |
     *  | "true"   | true      | true      |
     *  | "false"  | true      | false     |
     *  | [1,2]    | true      | true      |
     *  | []       | false     | false     |
     *  | stdClass | true      | true      |
     *  +----------+-----------+-----------+
     *
     * @param boolean|int|string|null $var
     *
     * @return boolean
     */
    function boolean(bool|int|string|null $var): bool
    {
        if (is_bool($var)) {
            return ($var == true);
        }

        if (is_string($var)) {
            $var = strtolower($var);

            return match ($var) {
                'true', 'on', 'yes', 'y', '1' => true,
                default => false,
            };
        }

        if (is_numeric($var)) {
            return ($var == 1);
        }

        return boolval($var);
    }
}

if(!function_exists('config')) {
    /**
     * @param string $key
     * @param float|bool|int|string|null $default
     * @return float|bool|int|string|null
     */
    function config(string $key, float|bool|int|string $default = null): float|bool|int|string|null
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        } else {
            return $default;
        }
    }
}

if(!function_exists('app_echo')) {
    /**
     * @param string $message
     * @param array $context
     */
    function app_echo(string $message, array $context = []): void
    {
        if (is_array($context) && !empty($context)) {
            $message .= ' ' . json_encode($context);
        }

        echo trim($message) . "\n";
    }
}
