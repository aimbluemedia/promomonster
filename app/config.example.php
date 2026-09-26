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

    // Signs unsubscribe links, which have to keep working from an email
    // somebody archived a year ago. Generate one once and never change it:
    // changing it invalidates every unsubscribe link already in the wild, and
    // a link that 404s is how a complaint becomes a spam report.
    //
    //   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
    'app_key' => '',

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

    // Sending review requests.
    //
    // PHP's mail() is not an option: shared hosting sends it from a shared IP
    // with no DKIM signature of ours, and a review request in the spam folder
    // is worse than one never sent.
    //
    // driver  'postmark' to send for real, 'log' to write the message to
    //         storage/logs/mail.log instead, 'null' to accept and discard.
    //         Leave it empty and it picks 'postmark' when a token is set and
    //         'log' when it is not — so a half-configured install is visibly
    //         local rather than quietly broken.
    // token   Postmark SERVER token, not the account token.
    // from    The address on the envelope. Use a subdomain you do not send
    //         password resets from: every free user's spam complaints land on
    //         this domain's reputation, and if it gets blocklisted you lose
    //         the ability to log people in.
    // stream  Postmark message stream. Review requests are not transactional
    //         in Postmark's sense, so they belong on a broadcast stream.
    'mail' => [
        'driver' => '',
        'token'  => '',
        'from'   => 'reviews@notify.promomonster.com',
        'stream' => 'broadcast',

        // Shared secret on the delivery webhook URL, so only the provider can
        // post bounces and complaints to it:
        //   https://promomonster.com/webhooks/email/<this value>
        'webhook_secret' => '',
    ],

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
