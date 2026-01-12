<?php namespace Seiger\sApi\Logging;

final class AccessLogLevelResolver
{
    /**
     * @return 'debug'|'info'|'notice'|'warning'|'error'|'critical'|'alert'|'emergency'
     */
    public static function resolve(int $status): string
    {
        $levels = config('sapi.access.levels', []);
        $levels = is_array($levels) ? $levels : [];

        $explicit = $levels[(string)$status] ?? null;
        if (is_string($explicit) && $explicit !== '') {
            return self::normalize($explicit);
        }

        if ($status >= 200 && $status <= 299) {
            return self::normalize((string)($levels['2xx'] ?? 'info'));
        }

        if ($status >= 300 && $status <= 399) {
            return self::normalize((string)($levels['3xx'] ?? 'info'));
        }

        if ($status >= 400 && $status <= 499) {
            return self::normalize((string)($levels['4xx'] ?? 'notice'));
        }

        if ($status >= 500 && $status <= 599) {
            return self::normalize((string)($levels['5xx'] ?? 'error'));
        }

        return 'info';
    }

    /**
     * @return 'debug'|'info'|'notice'|'warning'|'error'|'critical'|'alert'|'emergency'
     */
    private static function normalize(string $level): string
    {
        $level = strtolower(trim($level));

        return match ($level) {
            'debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency' => $level,
            default => 'info',
        };
    }
}

