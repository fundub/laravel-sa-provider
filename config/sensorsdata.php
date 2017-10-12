<?php

return [

    'consumer_type' => 'file',

    /**
     * DebugConsumer,BatchConsumer,FileConsumer
     */
    'consumer' => [
        'queue' => [
            'name' => 'sensors_analytics_consumer',
            'redis' => [
                'driver'     => 'redis',
                'servers' => [
                    'host'     => '127.0.0.1',
                    'port'     => 6379,
                    'password' => '',
                    'database' => 1,
                    'timeout'  => 5,
                ]
            ],
        ],
        'file' => [
            'filename' => storage_path('logs/sensordata.log'),
        ],
        'batch' => [
            'server_url' => '',
            'max_size' => 50,
            'request_timeout' => 1000
        ],
        'debug' => [
            'server_url' => '',
            'write_data' => false,
            'request_timeout' => 1000
        ]
    ],
];
