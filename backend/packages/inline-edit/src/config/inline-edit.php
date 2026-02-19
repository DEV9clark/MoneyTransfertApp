<?php

return [
    'models' => [
        'client' => [
            'class' => \App\Models\Client::class,
            'rules' => [
                'name' => 'required|string|max:255',
                'email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:20',
            ],
        ],
        'transaction' => [
            'class' => \App\Models\Transaction::class,
            'rules' => [
                'status' => 'required|in:pending,completed,failed,cancelled',
                'reference' => 'required|string|max:255',
            ],
        ],
    ],
];
