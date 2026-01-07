<?php namespace Seiger\sApi\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @mixin \Seiger\sApi\sApi
 */
class sApi extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'sApi';
    }
}
