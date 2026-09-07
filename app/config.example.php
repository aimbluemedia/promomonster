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
];
