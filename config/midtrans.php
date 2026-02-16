<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Midtrans Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Midtrans Payment Gateway integration.
    | Make sure to set the correct credentials in your .env file.
    |
    */

    'server_key' => env('MIDTRANS_SERVER_KEY'),
    'client_key' => env('MIDTRANS_CLIENT_KEY'),
    'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
    'is_sanitized' => true,
    'is_3ds' => true,

    /*
    |--------------------------------------------------------------------------
    | Snap API URL
    |--------------------------------------------------------------------------
    |
    | The Snap API URL changes based on environment (sandbox/production).
    |
    */

    'snap_url' => env('MIDTRANS_IS_PRODUCTION', false)
        ? 'https://app.midtrans.com/snap/snap.js'
        : 'https://app.sandbox.midtrans.com/snap/snap.js',

    /*
    |--------------------------------------------------------------------------
    | Enabled Payment Methods
    |--------------------------------------------------------------------------
    |
    | Specify which payment methods to enable in Snap.
    | Available: credit_card, gopay, shopeepay, other_qris, bca_va, bni_va, bri_va, permata_va, etc.
    |
    */

    'enabled_payments' => [
        'credit_card',
        'gopay',
        'shopeepay',
        'other_qris',
        'bca_va',
        'bni_va',
        'bri_va',
        'permata_va',
    ],
];
