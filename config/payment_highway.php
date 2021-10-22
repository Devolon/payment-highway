<?php

return [
    'signature_key_id' => env('PAYMENT_HIGHWAY_SIGNATURE_KEY_ID', 'testKey'),
    'signature_secret' => env('PAYMENT_HIGHWAY_SIGNATURE_SECRET', 'testSecret'),
    'account' => env('PAYMENT_HIGHWAY_ACCOUNT', 'test'),
    'merchant' => env('PAYMENT_HIGHWAY_MERCHANT', 'test_merchantId'),
    'base_url' => env('PAYMENT_HIGHWAY_BASE_URL', 'https://v1-hub-staging.sph-test-solinor.com'),
    'language' => env('PAYMENT_HIGHWAY_LANGUAGE', 'EN'),
    'app_base_url' => config('app.url', 'http://localhost'),
];
