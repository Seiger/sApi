<?php namespace Seiger\sApi;

use Illuminate\Support\Str;

class sApi
{
    /**
     * Convert data to a string representation.
     *
     * @param mixed $data The data to convert.
     * @return string The string representation of the data.
     */
    public static function logPrepare(mixed $data): string
    {
        ob_start();
        var_dump($data);
        $data = ob_get_contents();
        ob_end_clean();

        $data = Str::of($data)->replaceMatches('/string\(\d+\) .*/', function ($match) {
            return substr($match[0], (strpos($match[0], ') ') + 2)) . ',';
        })->replaceMatches('/bool\(\w+\)/', function ($match) {
            return str_replace(['bool(', ')'], ['', ','], $match[0]);
        })->replaceMatches('/int\(\d+\)/', function ($match) {
            return str_replace(['int(', ')'], ['', ','], $match[0]);
        })->replaceMatches('/float\(\d+\.\d+\)/', function ($match) {
            return str_replace(['float(', ')'], ['', ','], $match[0]);
        })->replaceMatches('/array\(\d+\) /', function ($match) {
            return str_replace($match[0], '', $match[0]);
        })->replaceMatches('/=>\n[ \t]{1,}/', function () {
            return ' => ';
        })->replaceMatches('/  /', function () {
            return '    ';
        })->remove('[')->remove(']')->replace('{', '[')->replace('}', '],')->rtrim(",\n");

        return $data;
    }
}
