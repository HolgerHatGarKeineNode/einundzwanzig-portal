<?php

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Facade;

return [

    'super-admin' => env('SUPERADMIN', false),

    'user-timezone' => 'UTC',

    'country' => env('DEFAULT_LOCALE', 'de'),


    'aliases' => Facade::defaultAliases()->merge([
        // 'ExampleClass' => App\Example\ExampleClass::class,
    ])->toArray(),

];
