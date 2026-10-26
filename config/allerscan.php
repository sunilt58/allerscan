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

    // The shopper suggestion box and its team review page. Off for now; saved suggestions are kept.
    'suggestions' => (bool) env('SUGGESTIONS_ENABLED', false),

    /*
     * AI research for unregistered barcodes (Google Gemini, see services.gemini). Results are saved as
     * "AI estimate (unconfirmed)" products for the team to check. Needs GEMINI_API_KEY as well.
     */
    'ai' => [
        'enabled' => (bool) env('AI_LOOKUP_ENABLED', false),
        // New barcodes researched per day, across everyone, and per signed-in user or visitor.
        'daily_limit' => (int) env('AI_LOOKUP_DAILY_LIMIT', 100),
        'per_person_daily_limit' => (int) env('AI_LOOKUP_PER_PERSON_DAILY_LIMIT', 10),
    ],

    // Shown on the Privacy & terms page.
    'contact_email' => env('CONTACT_EMAIL'),
];
