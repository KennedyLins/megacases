<?php

namespace Webkul\MoloniIntegration\Providers;

use Illuminate\Support\ServiceProvider;

class MoloniServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/../Http/routes.php');
    }

    public function register()
    {
        //
    }
}
