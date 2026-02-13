<?php

namespace MoneyTransfer\InlineEdit;

use Illuminate\Support\ServiceProvider;

class InlineEditServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Publier les assets JS/CSS
        $this->publishes([
            __DIR__.'/../resources/js/inline-edit.js' => public_path('js/inline-edit.js'),
            __DIR__.'/../resources/css/inline-edit.css' => public_path('css/inline-edit.css'),
        ], 'inline-edit-assets');

        // Publier la configuration
        $this->publishes([
            __DIR__.'/config/inline-edit.php' => config_path('inline-edit.php'),
        ], 'inline-edit-config');

        // Charger les routes si activé
        if (config('inline-edit.auto_routes', false)) {
            $this->loadRoutesFrom(__DIR__.'/routes/api.php');
        }

        // Charger les vues (si vous en ajoutez)
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'inline-edit');
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Merger la configuration
        $this->mergeConfigFrom(
            __DIR__.'/config/inline-edit.php',
            'inline-edit'
        );

        // Enregistrer le contrôleur
        $this->app->bind(
            'inline-edit.controller',
            \MoneyTransfer\InlineEdit\Http\Controllers\InlineEditController::class
        );
    }
}
