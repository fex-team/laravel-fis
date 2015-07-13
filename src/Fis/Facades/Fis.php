<?php namespace Fis\Facades;

use Illuminate\Support\Facades\Facade;

class Fis extends Facade {
    protected static function getFacadeAccessor()
    {
        return 'fis';
    }
}