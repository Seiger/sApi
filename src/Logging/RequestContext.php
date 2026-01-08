<?php namespace Seiger\sApi\Logging;

final class RequestContext
{
    /**
     * @var array<string,mixed>
     */
    private static array $data = [];

    public static function set(string $key, mixed $value): void
    {
        self::$data[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$data[$key] ?? $default;
    }

    /**
     * @return array<string,mixed>
     */
    public static function all(): array
    {
        return self::$data;
    }
}
