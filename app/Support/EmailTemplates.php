<?php

declare(strict_types=1);

namespace App\Support;

use PDOException;

/**
 * The wording a business uses to ask for a review.
 *
 * Everything underneath this already existed: the templates table since 009,
 * the two system templates since 018, and Template::render() to merge the
 * fields in. What was missing was any way for a member to write their own, so
 * every request from every account went out in identical words -- which is a
 * problem beyond taste, because a plumber and a dentist do not sound alike and
 * a customer can tell when an email was not written for them.
 *
 * Two kinds of message, both editable: the request and the one reminder that
 * follows it. They are the same ask in two parts, so editing one and not the
 * other would read as two different businesses writing.
 *
 * What a member may NOT do here is anything that would make the ask
 * conditional. There is no "only send to customers who were happy", no rating
 * question before the link, and no branch in the template language to build one
 * with -- Template deliberately has no conditionals. Review gating is against
 * Google's policy and, since 2024, against 16 CFR Part 465 in the US. The
 * compliant path has to be the only path on offer, not the one we recommend.
 */
final class EmailTemplates
{
    /** The two message kinds, in the order they are sent. */
    public const KINDS = [
        'request'   => 'Review request',
        'follow_up' => 'Reminder',
    ];

    public const MAX_NAME    = 120;
    public const MAX_SUBJECT = 200;
    public const MAX_BODY    = 5000;

    /**
     * What a member may merge in, and what each one becomes.
     *
     * Shown on the page verbatim, because a merge field nobody knows about is
     * the same as one that does not exist. The descriptions are what the
     * customer will actually see, not what the column is called.
     */
    public const FIELDS = [
        'first_name'    => "The customer's first name, or \"there\" if we do not have one",
        'last_name'     => "Their last name, if you have it",
        'business_name' => 'Your business name',
        'review_url'    => 'The link to your review box. Every message needs this one',
        'city'          => 'The city on your location',
    ];

    /**
     * The wording the product ships with.
     *
     * A copy of what 018 seeds, and the templates-test asserts the two are
     * character-for-character identical so they cannot drift. The duplication
     * buys something worth more than it costs: 018's INSERTs are the only
     * place these words existed, so a database that never ran 018 -- or ran a
     * version of it before the seed was added -- had no request template at
     * all, and every attempt to ask a customer for a review stopped dead on
     * "No request template is installed. Run the migrations."
     *
     * That is a feature that cannot work until somebody runs SQL by hand, for
     * a product whose whole job is that one feature. install() puts the rows in
     * when they are missing, so the migration becomes the fast path rather than
     * the only path.
     *
     * Written to be read by somebody who has just had work done and is not
     * expecting an email: short, specific about who is asking, honest that it
     * takes a minute, and with no incentive of any kind. Offering anything in
     * exchange for a review breaks Google's policy outright, so the wording we
     * ship has to make the compliant path the easy one.
     *
     * Three for the request itself, because one template is not a choice and a
     * business asked to write its own from an empty box mostly does not. They
     * differ by situation rather than by tone, which is the axis that changes
     * whether a customer replies: the length somebody will read on a phone
     * between jobs is not the length that suits a first visit, and a customer
     * on their fourth job should not be greeted like a stranger.
     *
     * A list rather than a map keyed by kind, because there is now more than
     * one of a kind. The natural key is (kind, name), which is what install()
     * checks and what keeps 018's two rows from being inserted a second time --
     * so the first two names here must stay exactly as 018 spells them.
     *
     * None of them use {{city}}. It is a real field and members may use it, but
     * an account with no city set renders "people in  decide" with the gap
     * still in it, and the wording we ship should not depend on a field that
     * may be blank.
     */
    public const SYSTEM = [
        [
            // Seeded by 018 as well. The name is the key: changing it here
            // would insert a second copy alongside the migration's row.
            'kind'      => 'request',
            'migration' => '018',
            'name'      => 'Standard review request',
            'subject'   => 'How did we do, {{first_name}}?',
            'body'      => "Hi {{first_name}},\n"
                . "\n"
                . "Thanks for choosing {{business_name}}. It was good to work with you.\n"
                . "\n"
                . "If you have a minute, would you leave us a review? It is the main way\n"
                . "people find us, and honest feedback helps the next customer decide.\n"
                . "\n"
                . "{{review_url}}\n"
                . "\n"
                . "It takes about a minute, and you can say whatever you actually think.\n"
                . "\n"
                . "Thanks,\n"
                . '{{business_name}}',
        ],
        [
            // For a customer who will read this on a phone, standing next to
            // the work that was just finished. Four lines, one link, no
            // preamble: the shortest thing that is still polite.
            'kind'    => 'request',
            'name'    => 'Short and direct',
            'subject' => 'Quick favour, {{first_name}}?',
            'body'    => "Hi {{first_name}},\n"
                . "\n"
                . "Would you leave {{business_name}} a review? It takes a minute and it\n"
                . "really does help us.\n"
                . "\n"
                . "{{review_url}}\n"
                . "\n"
                . "Thanks,\n"
                . '{{business_name}}',
        ],
        [
            // For somebody who has used the business before. Saying so is the
            // whole point: a repeat customer who gets the same email as a
            // stranger learns that nobody noticed they came back.
            'kind'    => 'request',
            'name'    => 'For a repeat customer',
            'subject' => 'Thanks for coming back, {{first_name}}',
            'body'    => "Hi {{first_name}},\n"
                . "\n"
                . "Thanks for having {{business_name}} out again. Customers who keep\n"
                . "calling us are the reason we are still here.\n"
                . "\n"
                . "If you have a minute, would you put that in a review? It is what\n"
                . "people read when they are deciding who to trust with their own job.\n"
                . "\n"
                . "{{review_url}}\n"
                . "\n"
                . "Say whatever you actually think -- honest is more use to us than\n"
                . "glowing.\n"
                . "\n"
                . "Thanks,\n"
                . '{{business_name}}',
        ],
        [
            'kind'      => 'follow_up',
            'migration' => '018',
            'name'      => 'Standard reminder',
            'subject'   => 'A quick reminder, {{first_name}}',
            'body'      => "Hi {{first_name}},\n"
                . "\n"
                . "I sent you a note a few days ago about leaving {{business_name}} a\n"
                . "review. If you have already done it, thank you, and please ignore this.\n"
                . "\n"
                . "If not, the link is here:\n"
                . "\n"
                . "{{review_url}}\n"
                . "\n"
                . "This is the only reminder you will get from us.\n"
                . "\n"
                . "Thanks,\n"
                . '{{business_name}}',
        ],
    ];

    /** Sample values for the preview, so nobody has to send one to find out. */
    private const SAMPLE = [
        'first_name'    => 'Dana',
        'last_name'     => 'Reyes',
        'business_name' => 'Acme Pools',
        'review_url'    => 'https://promomonster.com/r/EXAMPLE',
        'city'          => 'Mesa',
    ];

    private static ?bool $ready = null;

    /** install() is worth attempting once per request, not once per lookup. */
    private static bool $installed = false;

    /**
     * Has migration 021 been applied?
     *
     * Same reasoning as HostedReviews::ready(): the files go up by FTP and the
     * migration is run by hand afterwards, so there is a window where the page
     * exists and the column does not. A page that says so beats a 500.
     */
    public static function ready(): bool
    {
        if (self::$ready !== null) {
            return self::$ready;
        }

        try {
            $row = Database::first(
                'SELECT COUNT(*) AS n FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c',
                ['t' => 'templates', 'c' => 'is_default'],
            );

            return self::$ready = ((int) ($row['n'] ?? 0)) === 1;
        } catch (PDOException) {
            return self::$ready = false;
        }
    }

    /**
     * Put the shipped templates in, if they are not already.
     *
     * Runs on the read path rather than as a migration, because the migration
     * is the part that did not happen. Cheap: one indexed count, and after the
     * first call in a request it does not ask again.
     *
     * Matched on (kind, name), not on kind: there are three request templates
     * now, so "a row of this kind already exists" would install the first one
     * and silently skip the other two. The name is the natural key, which is
     * also what stops 018's two rows being inserted a second time -- their
     * names here are spelled exactly as the migration spells them, and the
     * drift test holds that true.
     *
     * A row an account has written is never touched: only is_system rows with
     * no account and no vertical are considered. Renaming a shipped template is
     * therefore not a rename -- it is a new template, and the old one stays
     * where it is. That is the right way round: somebody may have made it their
     * default.
     *
     * Returns how many rows it added, for the test and for diagnose.php.
     */
    public static function install(): int
    {
        // One read of what is already there, rather than a count per template.
        // This runs on a page load, so four queries to decide to do nothing is
        // four more than it needs.
        try {
            $rows = Database::all(
                'SELECT kind, name FROM templates
                  WHERE is_system = 1 AND channel = :channel AND vertical IS NULL',
                ['channel' => 'email'],
            );
        } catch (PDOException) {
            // No templates table at all, which is 009 missing and a bigger
            // problem than this method can fix. The caller's own "not
            // installed" message is the right answer, so say nothing here.
            return 0;
        }

        $have = [];
        foreach ($rows as $row) {
            $have[self::key((string) $row['kind'], (string) $row['name'])] = true;
        }

        $added = 0;
        foreach (self::SYSTEM as $shipped) {
            if (isset($have[self::key($shipped['kind'], $shipped['name'])])) {
                continue;
            }

            try {
                // account_id NULL and is_system 1 is what makes it everybody's.
                // is_default stays 0: a system row is shared, so flagging it
                // would flag it for every account on the server. defaultFor()
                // falls through to it instead.
                Database::run(
                    'INSERT INTO templates (account_id, vertical, channel, kind, name, subject, body, is_system)
                     VALUES (NULL, NULL, :channel, :kind, :name, :subject, :body, 1)',
                    ['channel' => 'email', 'kind' => $shipped['kind'], 'name' => $shipped['name'],
                     'subject' => $shipped['subject'], 'body' => $shipped['body']],
                );
                $added++;
            } catch (PDOException) {
                return $added;
            }
        }

        return $added;
    }

    /** (kind, name) as one string. A NUL cannot occur in either. */
    private static function key(string $kind, string $name): string
    {
        return $kind . "\0" . $name;
    }

    /**
     * Make sure the shipped templates are in, at most once per request.
     *
     * Unconditional rather than only-when-something-is-missing, which is what
     * this did at first and was wrong: it asked whether a row of the kind
     * existed, and on any database that had run 018 the answer was yes, so the
     * two request templates added after 018 would never have been installed at
     * all. Whether SOME template exists is not the same question as whether the
     * ones we ship do.
     */
    private static function ensure(): void
    {
        if (self::$installed) {
            return;
        }
        self::$installed = true;
        self::install();
    }

    /**
     * The shipped template for one kind, installing it if it has gone missing.
     *
     * Every path that needs a system row comes through here, so there is one
     * place that knows a missing row is repairable rather than fatal.
     *
     * @return array<string,mixed>|null
     */
    private static function shipped(string $kind): ?array
    {
        self::ensure();

        return ReviewRequests::systemTemplate($kind);
    }

    /** For the tests, which install and uninstall around each case. */
    public static function forget(): void
    {
        self::$ready     = null;
        self::$installed = false;
    }

    // -- Reading ---------------------------------------------------------

    /**
     * Every template this account may send, of one kind.
     *
     * Their own first, then the shipped ones, because the list is also the order
     * the send form offers them in and a member's own wording is the more
     * likely choice. The shipped rows are always last and always present: they
     * are the floor the account falls back to and cannot delete itself below.
     *
     * Within each group, oldest first. That matters for the shipped ones: the
     * lowest id is what systemTemplate() returns, and therefore what an account
     * with no default of its own actually sends -- so sorting by name put the
     * template in use third of three, a list whose first entry is not the one
     * that goes out if you press Send.
     *
     * @return list<array<string,mixed>>
     */
    public static function forAccount(int $accountId, string $kind = 'request'): array
    {
        if (!self::ready()) {
            return [];
        }

        // "Always present" is a claim this has to make true rather than assume.
        // Installing here is what stops the page and the send form showing an
        // empty list on a database that never ran the seed -- the state that
        // made asking a customer for a review impossible until somebody ran SQL
        // by hand -- and what puts newly shipped templates in front of an
        // account that already had the older ones.
        self::ensure();

        return Database::all(
            'SELECT * FROM templates
              WHERE channel = :channel AND kind = :kind
                AND (account_id = :account OR (account_id IS NULL AND is_system = 1 AND vertical IS NULL))
           ORDER BY is_system ASC, is_default DESC, id ASC',
            ['channel' => 'email', 'kind' => self::kind($kind), 'account' => $accountId],
        );
    }

    /**
     * One template, if this account is allowed to see it.
     *
     * The account check is the whole point of the method. A template id comes
     * off a form, and without this an id typed into the box would load somebody
     * else's wording -- and then send it, over their name, to a customer of
     * ours. Every read of a template by id goes through here.
     *
     * @return array<string,mixed>|null
     */
    public static function find(int $accountId, int $id): ?array
    {
        if (!self::ready() || $id <= 0) {
            return null;
        }

        return Database::first(
            'SELECT * FROM templates
              WHERE id = :id AND channel = :channel
                AND (account_id = :account OR (account_id IS NULL AND is_system = 1))
              LIMIT 1',
            ['id' => $id, 'channel' => 'email', 'account' => $accountId],
        );
    }

    /**
     * The one that gets used when nobody chose.
     *
     * The account's own default if it has set one, and the system template if
     * it has not -- which is every account until the first time somebody opens
     * this page. No row has to exist for a new account for the answer to be
     * right, which is why is_default lives only on account-owned rows.
     *
     * @return array<string,mixed>|null
     */
    public static function defaultFor(int $accountId, string $kind = 'request'): ?array
    {
        if (!self::ready()) {
            return self::shipped(self::kind($kind));
        }

        $own = Database::first(
            'SELECT * FROM templates
              WHERE account_id = :account AND channel = :channel AND kind = :kind AND is_default = 1
           ORDER BY id ASC LIMIT 1',
            ['account' => $accountId, 'channel' => 'email', 'kind' => self::kind($kind)],
        );

        return $own ?? self::shipped(self::kind($kind));
    }

    /**
     * The template to send, given what the form asked for.
     *
     * Falls back rather than failing: a member who picks a template and then
     * deletes it in another tab should still have their request go out, in the
     * default wording, rather than get an error about a row id. The one thing
     * it will not do is send a template belonging to another account, because
     * the lookup goes through find().
     *
     * @return array<string,mixed>|null
     */
    public static function resolve(int $accountId, ?int $id, string $kind = 'request'): ?array
    {
        if ($id !== null && $id > 0) {
            $chosen = self::find($accountId, $id);
            if ($chosen !== null && (string) $chosen['kind'] === self::kind($kind)) {
                return $chosen;
            }
        }

        return self::defaultFor($accountId, $kind);
    }

    // -- Writing ---------------------------------------------------------

    /**
     * Create or update one of the account's own templates.
     *
     * @param array{id?:?int, kind:string, name:string, subject:string, body:string, make_default?:bool} $input
     * @return array{ok:bool, id:?int, error:?string}
     */
    public static function save(int $accountId, array $input): array
    {
        if (!self::ready()) {
            return self::no('Email templates are not switched on yet.');
        }

        $kind    = self::kind((string) $input['kind']);
        $name    = trim($input['name']);
        $subject = trim($input['subject']);
        $body    = trim($input['body']);

        if ($name === '') {
            return self::no('Give the template a name, so you can tell it apart from the others.');
        }
        if (mb_strlen($name) > self::MAX_NAME) {
            return self::no('That name is too long.');
        }
        if ($subject === '') {
            return self::no('An email needs a subject line.');
        }
        if (mb_strlen($subject) > self::MAX_SUBJECT) {
            return self::no('That subject line is too long.');
        }
        if ($body === '') {
            return self::no('The message is empty.');
        }
        if (mb_strlen($body) > self::MAX_BODY) {
            return self::no('That message is longer than we can store. Please shorten it.');
        }

        // Without the link the email is an email about nothing: the customer
        // reads a request to leave a review and has no way to leave one. Worth
        // refusing rather than sending, because the send looks successful.
        if (!str_contains($body, '{{review_url}}')) {
            return self::no('The message needs {{review_url}} in it somewhere, or there is nothing '
                . 'for the customer to click.');
        }

        $unknown = self::unknownFields($subject . "\n" . $body);
        if ($unknown !== []) {
            return self::no('This does not know what to do with {{' . implode('}}, {{', $unknown)
                . '}}, so it would be deleted before sending. Available fields are listed below.');
        }

        $existing = isset($input['id']) && (int) $input['id'] > 0
            ? self::find($accountId, (int) $input['id'])
            : null;

        // Editing a system template edits it for every account on the server.
        // Saving a copy is what was meant, and is what happens.
        if ($existing !== null && (int) $existing['is_system'] === 1) {
            $existing = null;
        }

        if ($existing !== null) {
            Database::run(
                'UPDATE templates SET name = :name, subject = :subject, body = :body, updated_at = NOW()
                  WHERE id = :id AND account_id = :account',
                ['name' => $name, 'subject' => $subject, 'body' => $body,
                 'id' => (int) $existing['id'], 'account' => $accountId],
            );
            $id = (int) $existing['id'];
        } else {
            Database::run(
                'INSERT INTO templates (account_id, vertical, channel, kind, name, subject, body,
                                        is_system, is_default, updated_at)
                 VALUES (:account, NULL, :channel, :kind, :name, :subject, :body, 0, 0, NOW())',
                ['account' => $accountId, 'channel' => 'email', 'kind' => $kind,
                 'name' => $name, 'subject' => $subject, 'body' => $body],
            );
            $id = (int) Database::connection()->lastInsertId();
        }

        if (!empty($input['make_default'])) {
            self::makeDefault($accountId, $id);
        }

        return ['ok' => true, 'id' => $id, 'error' => null];
    }

    /**
     * Point this account's default at one of its own templates.
     *
     * Two statements, and the order matters: clear, then set. Setting first
     * would leave two rows flagged for the instant between them, and
     * defaultFor() takes the lowest id -- so a crash in the gap would leave the
     * account on the wrong one silently. Clearing first can only ever leave it
     * on the system template, which is the safe end to fail towards.
     */
    public static function makeDefault(int $accountId, int $id): bool
    {
        $row = self::find($accountId, $id);

        // A system template cannot be flagged, because the row is shared with
        // every other account. It does not need to be: it is already what
        // defaultFor() returns once the account has no default of its own, so
        // clearing the flag IS choosing it.
        if ($row === null || (int) $row['is_system'] === 1) {
            return false;
        }

        Database::run(
            'UPDATE templates SET is_default = 0
              WHERE account_id = :account AND channel = :channel AND kind = :kind',
            ['account' => $accountId, 'channel' => 'email', 'kind' => (string) $row['kind']],
        );
        Database::run(
            'UPDATE templates SET is_default = 1 WHERE id = :id AND account_id = :account',
            ['id' => $id, 'account' => $accountId],
        );

        return true;
    }

    /**
     * Hand the account back to the system wording for one kind.
     *
     * Clearing every flag is the whole operation: with none of its own set,
     * defaultFor() returns the system template again.
     */
    public static function useSystemDefault(int $accountId, string $kind): void
    {
        if (!self::ready()) {
            return;
        }

        Database::run(
            'UPDATE templates SET is_default = 0
              WHERE account_id = :account AND channel = :channel AND kind = :kind',
            ['account' => $accountId, 'channel' => 'email', 'kind' => self::kind($kind)],
        );
    }

    /**
     * Delete one of the account's own templates.
     *
     * Requests already sent keep working: review_requests.template_id is ON
     * DELETE SET NULL, and the sender reads the subject and body off the joined
     * row, so a queued request whose template has just been deleted would go
     * out empty. Queued rows are moved onto the default first.
     */
    public static function delete(int $accountId, int $id): bool
    {
        $row = self::find($accountId, $id);
        if ($row === null || (int) $row['is_system'] === 1) {
            return false;
        }

        // Stand it down as the default BEFORE asking what the default is.
        // Deleting the one that is currently in force is the ordinary case --
        // it is the one somebody has been using and has decided against -- and
        // asking first returns the row being deleted, which reads as "the
        // fallback is already right" and skips the hand-off below. The queued
        // requests then lose their wording to ON DELETE SET NULL and go out
        // with an empty subject and an empty body.
        Database::run(
            'UPDATE templates SET is_default = 0 WHERE id = :id AND account_id = :account',
            ['id' => $id, 'account' => $accountId],
        );

        $fallback = self::defaultFor($accountId, (string) $row['kind']);
        if ($fallback !== null && (int) $fallback['id'] !== $id) {
            Database::run(
                'UPDATE review_requests SET template_id = :new
                  WHERE template_id = :old AND status IN (\'queued\', \'scheduled\')',
                ['new' => (int) $fallback['id'], 'old' => $id],
            );
        }

        Database::run(
            'DELETE FROM templates WHERE id = :id AND account_id = :account AND is_system = 0',
            ['id' => $id, 'account' => $accountId],
        );

        return true;
    }

    // -- Showing ---------------------------------------------------------

    /**
     * The message as a customer would read it, with sample values merged in.
     *
     * Rendered by the same Template::render() the sender uses, not by a second
     * copy of the substitution rules -- a preview that renders differently to
     * the send is worse than no preview, because it is believed.
     *
     * @param array<string,mixed> $template
     * @return array{subject:string, body:string}
     */
    public static function preview(array $template): array
    {
        return [
            'subject' => Template::render((string) ($template['subject'] ?? ''), self::SAMPLE),
            'body'    => Template::render((string) ($template['body'] ?? ''), self::SAMPLE),
        ];
    }

    /**
     * Merge fields in the text that are not fields we know.
     *
     * Template::render() deletes an unknown field silently, which is right at
     * send time -- a half-merged sentence is worse than a short one -- and
     * wrong at save time, where the member is still in a position to fix the
     * typo. Caught here so {{firstname}} is a message on the form rather than a
     * gap in an email somebody has already received.
     *
     * @return list<string>
     */
    public static function unknownFields(string $text): array
    {
        preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $text, $found);

        $unknown = [];
        foreach ($found[1] ?? [] as $name) {
            $name = mb_strtolower($name);
            if (!array_key_exists($name, self::FIELDS) && !in_array($name, $unknown, true)) {
                $unknown[] = $name;
            }
        }

        return $unknown;
    }

    // -- Internals -------------------------------------------------------

    /** Anything that is not one of the two kinds is a request. */
    private static function kind(string $kind): string
    {
        return array_key_exists($kind, self::KINDS) ? $kind : 'request';
    }

    /** @return array{ok:bool, id:?int, error:string} */
    private static function no(string $why): array
    {
        return ['ok' => false, 'id' => null, 'error' => $why];
    }
}
