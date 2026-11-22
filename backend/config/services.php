<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID'),              // Identifiant du compte
        'token' => env('TWILIO_AUTH_TOKEN'),     // Mot de passe API  
        'phone' => env('TWILIO_PHONE_NUMBER'),   // Numéro qui envoie les SMS
        'whatsapp' => env('TWILIO_WHATSAPP_NUMBER'), // Numéro WhatsApp
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Ordre d'essai des prestataires SMS (le suivant sert de secours si le précédent échoue).
    'sms' => [
        'drivers' => env('SMS_DRIVERS', 'dexnotify,dexchange'),
    ],

    'dexnotify' => [
        'url' => env('DEXNOTIFY_URL', 'https://api.dexnotify.com/api'),
        'api_key' => env('DEXNOTIFY_API_KEY'),
        'sender' => env('DEXNOTIFY_SENDER', 'DEXCHANGE'),
        'timeout' => (int) env('DEXNOTIFY_TIMEOUT', 10),
        // WhatsApp (facultatif) : copie du code de retrait via un modèle validé par Meta.
        'whatsapp_account_id' => env('DEXNOTIFY_WHATSAPP_ACCOUNT_ID'),
        'whatsapp_withdrawal_template' => env('DEXNOTIFY_WHATSAPP_WITHDRAWAL_TEMPLATE'),
        'whatsapp_language' => env('DEXNOTIFY_WHATSAPP_LANGUAGE', 'fr'),
    ],

    // Ancienne API Dexchange SMS (secours)
    'dexchange' => [
        'api_key' => env('DEXCHANGE_API_KEY'),
        'signature' => env('DEXCHANGE_SIGNATURE', 'DEXCHANGE'),
        'endpoint' => env('DEXCHANGE_ENDPOINT', 'https://api.dexchange-sms.com/api/v1/send/sms'),
    ],

];
