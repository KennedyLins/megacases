<?php

namespace Webkul\Shipping\CTT;

use Illuminate\Support\ServiceProvider;

class CTTServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(CTTService::class, function () {
            return new CTTService();
        });
    }
}
