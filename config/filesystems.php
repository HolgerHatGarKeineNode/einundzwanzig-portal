<?php

return [

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'geo' => [
            'driver' => 'local',
            'root' => storage_path('app/geo'),
            'throw' => false,
        ],

        'lists' => [
            'driver' => 'local',
            'root' => storage_path('app/lists'),
            'throw' => false,
        ],

        'publicDisk' => [
            'driver' => 'local',
            'root' => public_path(),
            'throw' => false,
        ],
    ],

];
