<?php

return [
    'default_password' => 'AllerScanDemo2027!',
    'password' => env('DEMO_PASSWORD', 'AllerScanDemo2027!'),
    'allow_seed' => (bool) env('DEMO_ALLOW_SEED', false),
    'noindex' => (bool) env('DEMO_NOINDEX', true),
    'contact_email' => env('CONTACT_EMAIL'),
];
