<?php

namespace Amrshah\Arbac\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Amrshah\Arbac\Arbac
 */
class Arbac extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Amrshah\Arbac\ArbacManager::class;
    }
}
