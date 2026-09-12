<?php

declare(strict_types=1);

/**
 * Copy to app/config.php and fill in. config.php is gitignored — never commit
 * real credentials.
 */
return [
    'app_name' => 'PromoMonster',
    'app_url'  => 'https://promomonster.com',
    'debug'    => false,
    'timezone' => 'America/Phoenix',

    'db' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'database' => 'promomonster',
        'username' => '',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],

    // Where waitlist notifications go. Leave null to disable email.
    'notify_email' => null,

    // Powers the competitor comparison in the superadmin audit screen. Without
    // a key that one feature is disabled; nothing else depends on it.
    // Billed per use — roughly $0.05-$0.15 per audit.
    'anthropic' => [
        'api_key' => '',
    ],
];
