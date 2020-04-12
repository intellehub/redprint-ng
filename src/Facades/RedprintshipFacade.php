<?php

namespace Shahnewaz\Redprint\Facades;

use Illuminate\Support\Facades\Facade;

class RedprintshipFacade extends Facade
{
    protected static function getFacadeAccessor () {
        return 'redprintship';
    }
}
