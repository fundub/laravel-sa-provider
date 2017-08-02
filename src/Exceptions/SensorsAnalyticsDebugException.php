<?php

namespace Fundub\LaravelSaProvider\Exceptions;
use Exception;

/**
 * 当且仅当DEBUG模式中，任何网络错误、数据异常等都会抛出此异常，用户可不捕获，用于测试SDK接入正确性
 * Class SensorsAnalyticsDebugException
 * @package Fundub\LaravelSaProvider\Exception
 */
class SensorsAnalyticsDebugException extends Exception
{

}