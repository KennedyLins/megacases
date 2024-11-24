<?php

namespace Megacases\DHLShipping\Providers;

use Illuminate\Support\ServiceProvider;
use Webkul\Shipping\Shipping as DhlShipping;

class DHLShippingServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'dhl');
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->registerConfig();
    }

    /**
     * Register Bouncer as a singleton.
     */
    protected function registerFacades(): void
    {
        $this->app->singleton('shipping', function () {
            return new DhlShipping;
        });
    }

    /**
     * Register package config.
     */
    protected function registerConfig(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/carriers.php', 'carriers'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/system.php', 'core'
        );
    }
}
