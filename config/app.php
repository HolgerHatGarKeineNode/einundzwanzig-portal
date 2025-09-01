<?php

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Facade;

return [

    'super-admin' => env('SUPERADMIN', false),

    'user-timezone' => 'UTC',


    'aliases' => Facade::defaultAliases()->merge([
        // 'ExampleClass' => App\Example\ExampleClass::class,
    ])->toArray(),

];
