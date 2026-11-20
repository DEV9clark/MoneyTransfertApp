<?php

/*
|--------------------------------------------------------------------------
| Règles métier des transferts
|--------------------------------------------------------------------------
| Toutes les valeurs sont surchargeables dans le fichier .env.
*/

return [

    // Code de retrait envoyé par SMS au bénéficiaire (chiffres uniquement).
    'code_length' => (int) env('TRANSFER_CODE_LENGTH', 8),

    // Durée de validité d'un envoi non retiré. Passé ce délai il est expiré et l'agent remboursé.
    'code_ttl_days' => (int) env('TRANSFER_CODE_TTL_DAYS', 30),

    // Nombre maximum de codes erronés par agent avant blocage temporaire.
    'max_code_attempts' => (int) env('TRANSFER_MAX_CODE_ATTEMPTS', 5),
    'code_attempts_decay_minutes' => (int) env('TRANSFER_CODE_ATTEMPTS_DECAY', 15),

    // NE JAMAIS activer en production : renvoie le code de retrait à l'agent expéditeur (utile en dev sans SMS).
    'show_code_to_agent' => (bool) env('TRANSFER_SHOW_CODE_TO_AGENT', false),

    // Plafonds (lutte anti-blanchiment). 0 = pas de plafond.
    'max_amount_per_transfer' => (float) env('TRANSFER_MAX_AMOUNT', 2000000),
    'daily_limit_per_client' => (float) env('TRANSFER_DAILY_LIMIT_PER_CLIENT', 3000000),

    // Pièce d'identité de l'expéditeur obligatoire à l'envoi.
    'require_sender_id' => (bool) env('TRANSFER_REQUIRE_SENDER_ID', false),

    // Types de pièces acceptés.
    'id_types' => [
        'cni' => "Carte nationale d'identité",
        'passport' => 'Passeport',
        'permis' => 'Permis de conduire',
        'carte_consulaire' => 'Carte consulaire',
        'carte_sejour' => 'Carte de séjour',
        'autre' => 'Autre',
    ],

    // Devise des pré-envois créés depuis l'application client.
    'default_currency' => env('TRANSFER_DEFAULT_CURRENCY', 'XOF'),

    // Pré-envois (application client) : délai pour payer en agence et nombre maximal en attente.
    'preorder_ttl_hours' => (int) env('TRANSFER_PREORDER_TTL_HOURS', 48),
    'max_open_preorders' => (int) env('TRANSFER_MAX_OPEN_PREORDERS', 5),

    // Application client : code PIN et vérification SMS.
    'client' => [
        'pin_length' => 4,
        'pin_max_attempts' => (int) env('CLIENT_PIN_MAX_ATTEMPTS', 5),
        'pin_lock_minutes' => (int) env('CLIENT_PIN_LOCK_MINUTES', 15),
        'otp_ttl_minutes' => (int) env('CLIENT_OTP_TTL_MINUTES', 10),
        'otp_max_attempts' => 5,
        // NE JAMAIS activer en production : renvoie le code SMS dans la réponse (dev sans SMS).
        'otp_debug' => (bool) env('CLIENT_OTP_DEBUG', false),
    ],

    // Informations imprimées en en-tête des reçus.
    'company' => [
        'name' => env('COMPANY_NAME', 'Money Transfer'),
        'address' => env('COMPANY_ADDRESS', 'Dakar, Sénégal'),
        'phone' => env('COMPANY_PHONE', ''),
        'email' => env('COMPANY_EMAIL', ''),
        'legal' => env('COMPANY_LEGAL', ''), // ex : "NINEA 000000000 - RCCM SN-DKR-2026-B-0000"
    ],
];
