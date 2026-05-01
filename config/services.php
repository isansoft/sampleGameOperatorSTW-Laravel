<?php

return [
    'prime_mac' => [
        'base_url' => env('PRIME_MAC_PROVIDER_BASE_URL', 'https://api.primemacgames.com'),
        'provider_code' => env('PRIME_MAC_PROVIDER_CODE', 'Prime Mac Games'),
        'operator_public_id' => env('PRIME_MAC_OPERATOR_PUBLIC_ID'),
        'signing_secret' => env('PRIME_MAC_SIGNING_SECRET'),
        'signature_drift_ms' => (int) env('PRIME_MAC_WALLET_SIGNATURE_DRIFT_MS', 60000),
        'provider_code_auto_sync' => filter_var(env('PRIME_MAC_PROVIDER_CODE_AUTO_SYNC', true), FILTER_VALIDATE_BOOL),
        'livekit_frame_origin' => env('PRIME_MAC_LIVEKIT_FRAME_ORIGIN', 'https://livekit.poker.goscanqr.com'),
    ],
];
