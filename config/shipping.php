<?php

return [
    'ctt' => [
        'api_key' => env('CTT_API_KEY'),
        'base_url' => env('CTT_BASE_URL', 'https://api.ctt.pt'),
    ],

    'providers' => [
        Webkul\Shipping\CTT\CTTServiceProvider::class,
    ],
];
