<?php

return [
    'prime_mac' => [
        'base_url' => env('PRIME_MAC_PROVIDER_BASE_URL', 'https://api.primemacgames.com'),
        'provider_code' => env('PRIME_MAC_PROVIDER_CODE', 'Prime Mac Games'),
        'operator_public_id' => env('PRIME_MAC_OPERATOR_PUBLIC_ID'),
        'signing_secret' => env('PRIME_MAC_SIGNING_SECRET'),
        'signature_drift_ms' => (int) env('PRIME_MAC_WALLET_SIGNATURE_DRIFT_MS', 60000),
    ],
];
