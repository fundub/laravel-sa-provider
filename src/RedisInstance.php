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

    static public function getInstance($server, $options)
    {
        if (is_null(self::$instance)) {
            self::$instance = self::getConnect($server, $options);
        }
        return self::$instance;
    }

    public static function getConnect($servers, $options)
    {
        $cluster = array();
        foreach ($servers as $key => $server) {
            $host = empty($server['host']) ? '127.0.0.1' : $server['host'];
            $port = empty($server['port']) ? '6379' : $server['port'];
            $cluster[] = "{$host}:{$port}";
        }

        $RedisCluster = new \RedisCluster(null, $cluster, $options['read_timeout'], $options['timeout'], $options['persistent']);

        //只发送到主节点
        //$RedisCluster->setOption(\RedisCluster::OPT_SLAVE_FAILOVER, \RedisCluster::FAILOVER_NONE);

        //当主节点挂掉，发送到你从节点
        //$RedisCluster->setOption(\RedisCluster::OPT_SLAVE_FAILOVER, \RedisCluster::FAILOVER_ERROR);

        //随机发送到 主从节点
        $RedisCluster->setOption(\RedisCluster::OPT_SLAVE_FAILOVER, \RedisCluster::FAILOVER_DISTRIBUTE);
        return $RedisCluster;
    }
}