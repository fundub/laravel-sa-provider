<?php

namespace Fundub\LaravelSaProvider;

class RedisInstance
{
    private static $instance = null;

    private function __construct()
    {

    }

    private function __clone()
    {

    }

    static public function getInstance($server)
    {
        if (is_null(self::$instance)) {
            self::$instance = self::getConnect($server);
        }
        return self::$instance;
    }

    public static function getConnect($server)
    {
        $redis = new \Redis;
        $redis->connect($server['host'], $server['port'], $server['timeout']);
        $redis->auth($server['password']);
        $redis->select($server['database']);
        return $redis;
    }
}