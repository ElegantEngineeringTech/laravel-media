<?php

namespace Finller\LaravelMedia\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Finller\LaravelMedia\LaravelMedia
 */
class LaravelMedia extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Finller\LaravelMedia\LaravelMedia::class;
    }
}
