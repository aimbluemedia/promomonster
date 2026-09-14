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

    // Powers the review comparison (superadmin audits and the public /compare
    // page). Without a key those are disabled; nothing else depends on it.
    'anthropic' => [
        'api_key' => '',

        // Which model runs the comparison. Per million tokens, in / out:
        //   claude-sonnet-5   $2 / $10   the default — the job is
        //                                instruction-following, not deep
        //                                reasoning, and this does it well
        //   claude-haiku-4-5  $1 / $5    cheapest, but measurably less
        //                                accurate; fine if you read every
        //                                report before it goes out
        //   claude-opus-5     $5 / $25   best judgement, for when a report
        //                                goes straight to a customer unread
        // Roughly $0.02 a comparison on Sonnet 5, $0.01 on Haiku, $0.05 on Opus.
        // An unrecognised value falls back to the default rather than failing.
        'model' => 'claude-sonnet-5',

        // How hard the model works before answering: low, medium, high, xhigh,
        // max. Ignored on Haiku 4.5, which does not accept the parameter.
        'effort' => 'medium',
    ],
];
