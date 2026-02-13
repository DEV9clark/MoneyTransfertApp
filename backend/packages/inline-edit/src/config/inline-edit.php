<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Inline Edit Enabled
    |--------------------------------------------------------------------------
    |
    | Activer ou désactiver globalement l'édition inline
    |
    */
    'enabled' => env('INLINE_EDIT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware à appliquer sur les routes d'édition inline
    |
    */
    'middleware' => ['auth:sanctum'],

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration des routes automatiques
    |
    */
    'auto_routes' => false, // Mettre à true pour charger les routes automatiquement
    'route_prefix' => 'api',
    'route_name_prefix' => 'inline-edit',

    /*
    |--------------------------------------------------------------------------
    | Default HTTP Method
    |--------------------------------------------------------------------------
    |
    | Méthode HTTP par défaut pour les mises à jour (PATCH ou PUT)
    |
    */
    'default_method' => 'PATCH',

    /*
    |--------------------------------------------------------------------------
    | Toast Notifications
    |--------------------------------------------------------------------------
    |
    | Afficher les notifications toast par défaut
    |
    */
    'show_toast' => true,

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    |
    | Règles de validation par défaut pour les champs courants
    |
    */
    'validation_rules' => [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255'],
        'phone' => ['required', 'string', 'max:20'],
        'amount' => ['required', 'numeric', 'min:0'],
        'status' => ['required', 'string'],
        'notes' => ['nullable', 'string', 'max:500'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Models
    |--------------------------------------------------------------------------
    |
    | Liste des modèles autorisés pour l'édition inline
    | Laisser vide pour autoriser tous les modèles
    |
    */
    'allowed_models' => [
        // 'App\Models\Transaction',
        // 'App\Models\Client',
    ],

    /*
    |--------------------------------------------------------------------------
    | CSRF Protection
    |--------------------------------------------------------------------------
    |
    | Activer la protection CSRF (recommandé en production)
    |
    */
    'csrf_protection' => true,

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Nombre maximum de requêtes par minute
    |
    */
    'rate_limit' => 60,
];
