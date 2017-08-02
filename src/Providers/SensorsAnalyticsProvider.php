<?php

namespace Fundub\LaravelSaProvider\Providers;

use Illuminate\Foundation\Application as LaravelApplication;
use Laravel\Lumen\Application as LumenApplication;
use Illuminate\Support\ServiceProvider;
use Fundub\LaravelSaProvider\SensorsAnalytics\SensorsAnalytics;
use Fundub\LaravelSaProvider\Consumer\FileConsumer;
use Fundub\LaravelSaProvider\Consumer\BatchConsumer;
use Fundub\LaravelSaProvider\Consumer\DebugConsumer;

class SensorsAnalyticsProvider extends ServiceProvider
{
    /**
     * 服务提供者加是否延迟加载.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $configPath = __DIR__ . '/../../config/sensorsdata.php';
        if ($this->app instanceof LaravelApplication) {
            $this->publishes([$configPath => config_path('sensorsdata.php')], 'config');
        } elseif ($this->app instanceof LumenApplication) {
            $this->app->configure('sensorsdata');
        }

        $this->mergeConfigFrom($configPath, 'sensorsdata');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $provider = $this;
        $this->app->singleton('sa', function () use ($provider) {
            return new SensorsAnalytics($provider->getConsumer());
        });

        $this->app->alias('sa', 'Fundub\LaravelSaProvider\SensorsAnalytics\SensorsAnalytics');
    }

    public function getConsumer()
    {
        $class = $this->app['config']->get('sensorsdata.consumer_type', 'file');
        switch ($class) {
            case 'file':
                $filename = $this->app['config']->get('sensorsdata.consumer.file.filename', 'sensors_analytics');
                return new FileConsumer($filename);
                break;
            case 'batch':
                $batchConfig = $this->app['config']->get('sensorsdata.consumer.batch');
                return new BatchConsumer($batchConfig['server_url'], $batchConfig['max_size'], $batchConfig['request_timeout']);
                break;
            case 'debug':
                $debugConfig = $this->app['config']->get('sensorsdata.consumer.debug');
                return new DebugConsumer($debugConfig['server_url'], $debugConfig['write_data'], $debugConfig['request_timeout']);
                break;
            default:
                return $this->app->make($class);
                break;
        }
    }

    /**
     * 获取由提供者提供的服务.
     *
     * @return array
     */
    public function provides()
    {
        return ['sa'];
    }
}