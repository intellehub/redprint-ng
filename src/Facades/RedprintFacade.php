<?php

namespace Shahnewaz\RedprintNg\Facades;

use Illuminate\Support\Facades\Facade;

class RedprintFacade extends Facade
{
    protected static function getFacadeAccessor () {
        return 'redprint';
    }
}
