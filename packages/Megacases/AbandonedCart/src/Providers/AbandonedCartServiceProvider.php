<?php

namespace Megacases\AbandonedCart\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Megacases\AbandonedCart\Observers\CartObserver;
use Webkul\Checkout\Models\Cart;

class AbandonedCartServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadRoutesFrom(__DIR__.'/../Routes/admin-routes.php');

        $this->loadRoutesFrom(__DIR__.'/../Routes/shop-routes.php');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'abandonedcart');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'abandonedcart');

        Event::listen('bagisto.admin.layout.head', function ($viewRenderEventManager) {
            $viewRenderEventManager->addTemplate('abandonedcart::admin.layouts.style');
        });

        Cart::observe(CartObserver::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Megacases\AbandonedCart\Console\Commands\SendAbandonedCartEmailsCommand::class,
            ]);
        }
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind('Megacases\AbandonedCart\Repositories\AbandonedCartRepository', function ($app) {
            return new \Megacases\AbandonedCart\Repositories\AbandonedCartRepository($app);
        });

        $this->registerConfig();
    }

    /**
     * Register package config.
     */
    protected function registerConfig(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/admin-menu.php', 'menu.admin'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/acl.php', 'acl'
        );

        $this->mergeConfigFrom(
            __DIR__.'/../Config/system.php', 'core_config'
        );
    }
}
