<?php

return [
    /*
     * The first team account, created by `php artisan db:seed`. Outside local and testing
     * environments the seeder refuses to run until ADMIN_PASSWORD is changed from the default.
     */
    'admin' => [
        'email' => env('ADMIN_EMAIL', 'admin@allerscan.test'),
        'password' => env('ADMIN_PASSWORD', env('DEMO_PASSWORD', 'AllerScanDemo2027!')),
        'default_password' => 'AllerScanDemo2027!',
    ],

    // Sends X-Robots-Tag: noindex. Turn off once the catalog is ready for search engines.
    'noindex' => (bool) env('APP_NOINDEX', env('DEMO_NOINDEX', true)),

    // Shown on the Privacy & terms page.
    'contact_email' => env('CONTACT_EMAIL'),
];
