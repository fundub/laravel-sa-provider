<?php

return [

    'consumer_type' => 'file',

    /**
     * DebugConsumer,BatchConsumer,FileConsumer
     */
    'consumer' => [
        'queue' => [
            'name' => 'sensors_analytics_consumer',
            'cluster' => [
                // redis cluster options
                'options' => [
                    'read_timeout'  => 1.5,
                    'timeout'       => 1.5,
                    'persistent'    => false,
                ],
                // put your cluster master node here
                [
                    'host' => '10.25.174.153',
                    'port' => 7001,
                ],
                [
                    'host' => '10.25.174.153',
                    'port' => 7002,
                ],
                [
                    'host' => '10.25.174.153',
                    'port' => 7003,
                ],
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
