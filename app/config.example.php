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

        // Account email: password resets. Deliberately NOT the two settings
        // above.
        //
        // 'from' carries every free user's spam complaints, and a blocklisted
        // domain there costs you review requests. On the same domain it would
        // also cost you the ability to let a locked-out customer back in, which
        // is the one email that has to arrive. Different subdomain, different
        // Postmark stream, separate reputations.
        //
        // Both addresses need their domain verified in Postmark. Leave
        // transactional_from empty and resets fall back to 'from' above --
        // it sends, but on one reputation; diagnose.php will say so.
        //
        // The stream is Postmark's default transactional one, which exists on
        // every server. Do not point it at the broadcast stream.
        'transactional_from'   => 'logins@promomonster.com',
        'transactional_stream' => 'outbound',

        // Account email can go through a different provider entirely, and on a
        // small site it probably should to begin with. Leave this empty to use
        // 'driver' above; set it to 'smtp' to send password resets through an
        // ordinary mailbox at your host.
        //
        // That is a sensible place to start: Hostinger, cPanel, Fastmail,
        // anything with a mailbox and SMTP. It needs no account to open and no
        // DNS to wait on, the daily allowance is far more than password resets
        // will ever use, and the mail is signed by whatever the host signs with.
        //
        // It is NOT where review requests belong. Those go out in bulk on
        // behalf of businesses, to people who never asked us for anything, and
        // a share of them press "report spam". A shared mailbox has no
        // complaint feedback loop and no bounce webhook, so the suppression
        // list would never learn who to stop emailing -- and sending bulk from
        // a hosting mailbox is how that mailbox gets suspended. Keep 'driver'
        // on Postmark for those.
        'transactional_driver' => '',

        // Only read when transactional_driver is 'smtp'.
        //
        // port/encryption: 465 is implicit TLS, 587 is STARTTLS. Leave
        // encryption empty and it is chosen from the port, which is one fewer
        // thing to get wrong.
        //
        // username is the full mailbox address, and it is also what the message
        // is posted as -- so make it the same address as transactional_from, or
        // the host will refuse it.
        //
        // The certificate is always verified. There is no flag to turn that
        // off: this password crosses the wire inside the tunnel, so an
        // unverified tunnel hands it to whoever answered.
        'smtp' => [
            'host'       => 'smtp.hostinger.com',
            'port'       => 465,
            'encryption' => '',
            'username'   => 'logins@promomonster.com',
            'password'   => '',
        ],

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
