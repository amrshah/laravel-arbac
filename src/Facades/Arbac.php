<?php

namespace Amrshah\Arbac\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Amrshah\Arbac\ArbacManager
 */
class Arbac extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'arbac';
    }
}
