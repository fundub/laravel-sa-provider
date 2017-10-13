<?php

namespace Fundub\LaravelSaProvider\Consumers;

use Fundub\LaravelSaProvider\AbstractConsumer;
use Fundub\LaravelSaProvider\RedisInstance;

class QueueConsumer extends AbstractConsumer
{

    private $redisInstance;

    private $queueName;

    public function __construct($server, $queueName)
    {
        $this->redisInstance = RedisInstance::getInstance($server);
        $this->queueName = $queueName;
    }

    public function send($msg)
    {
        if ($this->redisInstance === null) {
            return false;
        }
        return $this->redisInstance->rpush($this->queueName, $msg) === false ? false : true;
    }

    public function close()
    {
        if ($this->redisInstance === null) {
            return false;
        }
        $this->redisInstance->close();
    }

    public function getRedisInstance()
    {
        return $this->redisInstance;
    }
}