<?php

return [

    'consumer_type' => 'file',

    /**
     * DebugConsumer,BatchConsumer,FileConsumer
     */
    'consumer' => [
        'file' => [
            'filename' => storage_path('sensordata.log'),
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
