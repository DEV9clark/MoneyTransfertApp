<?php

namespace MoneyTransfer\InlineEdit;

use Illuminate\Support\ServiceProvider;

class InlineEditServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/inline-edit.php', 'inline-edit'
        );
    }

    public function boot()
    {
        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../../routes/api-inline-edit-routes.php');

        // Publish config
        $this->publishes([
            __DIR__ . '/config/inline-edit.php' => config_path('inline-edit.php'),
        ], 'inline-edit-config');
    }
}
