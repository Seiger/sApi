<?php namespace Seiger\sApi;

class sApi
{
    public static function config(string $key, mixed $default = null): mixed
    {
        return config('seiger.settings.sApi.' . $key, $default);
    }

    public static function asset(string $file): string
    {
        return asset('site/' . ltrim($file, '/'));
    }
}
