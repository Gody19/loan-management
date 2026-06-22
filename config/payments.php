<?php

return [
    'mongike' => [
        'api_key' => env('MONGIKE_API_KEY'),
        'base_url' => env('MONGIKE_BASE_URL', 'https://mongike.com/api/v1'),
        'webhook_url' => env('MONGIKE_WEBHOOK_URL'),
    ],
];
