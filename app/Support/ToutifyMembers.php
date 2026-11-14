<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use Throwable;

/**
 * Toutify's side of the member record, kept on PromoMonster's side.
 *
 * Everything here is a CACHE of what Toutify said. Toutify owns the account;
 * these rows exist so PromoMonster can show a name without a round trip and
 * can join reviews to a person. Where the two disagree, Toutify wins.
 *
 * toutify_updated_at is what makes that safe. Webhooks arrive out of order --
 * a retry of an older event can land after a newer one -- so every write
 * compares the incoming updated_at against the stored one and refuses to go
 * backwards. Without it, a 24-hour retry of a stale "member.updated" would
 * quietly undo a name change.
 *
 * PromoMonster's own shape is users + accounts + account_users: a person, a
 * business, and a role joining them. A Toutify sub identifies the PERSON, so a
 * first sign-in creates all three, exactly as the old signup form did.
 */
final class ToutifyMembers
{
    /** @return array<string,mixed>|null */
    public static function bySub(string $sub): ?array
    {
        if ($sub === '') {
            return null;
        }

        return Database::first('SELECT * FROM users WHERE toutify_sub = :s LIMIT 1', ['s' => $sub]);
    }

    /**
     * Signs a member in for the first time, or updates what we hold.
     *
     * @param  array<string,mixed> $claims UserInfo (or ID token) claims
     * @return int the PromoMonster user id
     */
    public static function upsert(array $claims): int
    {
        $sub = (string) ($claims['sub'] ?? '');
        if ($sub === '') {
            throw new \RuntimeException('Cannot store a member with no subject.');
        }

        $existing = self::bySub($sub);

        if ($existing !== null) {
            self::apply($sub, $claims);

            return (int) $existing['id'];
        }

        return self::create($sub, $claims);
    }

    /**
     * Creates the person, the business and the link between them.
     *
     * In one transaction: a user row with an account but no account_users row
     * is a member who can sign in and see nothing, which looks like the
     * product is broken rather than like a half-finished insert.
     *
     * @param array<string,mixed> $claims
     */
    private static function create(string $sub, array $claims): int
    {
        $name     = trim((string) ($claims['name'] ?? ''));
        $email    = strtolower(trim((string) ($claims['email'] ?? '')));
        $business = trim((string) ($claims['business_name'] ?? '')) ?: ($name ?: 'My business');

        [$first, $last] = self::splitName($name !== '' ? $name : $email);

        $pdo = Database::connection();

        // The webhook wraps a whole event in a transaction, and PDO throws on
        // a nested beginTransaction. Join the one that is open rather than
        // starting another: the caller's rollback then covers this too.
        $own = !$pdo->inTransaction();
        if ($own) {
            $pdo->beginTransaction();
        }

        try {
            Database::run(
                "INSERT INTO users (toutify_sub, email, password_hash, first_name, last_name,
                                    toutify_name, business_name, phone, website, toutify_status,
                                    toutify_updated_at, is_admin, status, must_change_password,
                                    email_verified_at)
                 VALUES (:sub, :email, NULL, :first, :last, :name, :business, :phone, :website,
                         :tstatus, :tupdated, 0, 'active', 0, :verified)",
                [
                    'sub'      => $sub,
                    'email'    => $email,
                    'first'    => $first,
                    'last'     => $last,
                    'name'     => $name !== '' ? $name : null,
                    'business' => $business,
                    'phone'    => self::nullable($claims['phone_number'] ?? null),
                    'website'  => self::nullable($claims['website'] ?? null),
                    'tstatus'  => (string) ($claims['account_status'] ?? 'active'),
                    'tupdated' => self::updatedAt($claims),
                    // Toutify verifies the address before a password is ever
                    // set, so a member arriving here has already proved it.
                    'verified' => !empty($claims['email_verified']) ? date('Y-m-d H:i:s') : null,
                ],
            );
            $userId = (int) $pdo->lastInsertId();

            Database::run(
                "INSERT INTO accounts (name, plan, requested_plan, requested_plan_at, signup_ip)
                 VALUES (:name, 'free', NULL, NULL, :ip)",
                ['name' => $business, 'ip' => Request::ip()],
            );
            $accountId = (int) $pdo->lastInsertId();

            Database::run(
                "INSERT INTO account_users (account_id, user_id, role) VALUES (:a, :u, 'owner')",
                ['a' => $accountId, 'u' => $userId],
            );
            Database::run(
                'INSERT INTO locations (account_id, name) VALUES (:a, :name)',
                ['a' => $accountId, 'name' => $business],
            );

            if ($own) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($own) {
                $pdo->rollBack();
            }
            throw $e;
        }

        Audit::log('member.created_from_toutify', 'user', $userId);

        return $userId;
    }

    /**
     * Applies a profile to an existing member, newest-wins.
     *
     * Returns false when the incoming data is older than what we hold, which
     * is a normal outcome, not a failure: it is a retry of an event we have
     * already passed.
     *
     * @param array<string,mixed> $claims
     */
    public static function apply(string $sub, array $claims): bool
    {
        $row = self::bySub($sub);
        if ($row === null) {
            return false;
        }

        $incoming = self::updatedAt($claims);
        $held     = $row['toutify_updated_at'] === null ? null : (int) $row['toutify_updated_at'];

        if ($incoming !== null && $held !== null && $incoming < $held) {
            return false;
        }

        $name     = trim((string) ($claims['name'] ?? ''));
        [$first, $last] = self::splitName($name);

        $sets = [
            'email'          => strtolower(trim((string) ($claims['email'] ?? $row['email']))),
            'toutify_status' => (string) ($claims['account_status'] ?? $row['toutify_status'] ?? 'active'),
        ];

        // Only overwrite with something. Toutify omits empty values, and an
        // omitted phone number means "not set there", not "clear what you have".
        if ($name !== '') {
            $sets['first_name']   = $first;
            $sets['last_name']    = $last;
            $sets['toutify_name'] = $name;
        }
        foreach (['business_name' => 'business_name', 'phone' => 'phone_number', 'website' => 'website'] as $column => $claim) {
            $value = self::nullable($claims[$claim] ?? null);
            if ($value !== null) {
                $sets[$column] = $value;
            }
        }
        if ($incoming !== null) {
            $sets['toutify_updated_at'] = $incoming;
        }

        $assign = [];
        $params = ['sub' => $sub];
        foreach ($sets as $column => $value) {
            $assign[] = $column . ' = :' . $column;
            $params[$column] = $value;
        }

        Database::run(
            'UPDATE users SET ' . implode(', ', $assign) . ' WHERE toutify_sub = :sub',
            $params,
        );

        // The business name is the account's name here, and a member who
        // renames their business on Toutify expects to see it renamed.
        if (isset($sets['business_name']) && $sets['business_name'] !== '') {
            Database::run(
                'UPDATE accounts a JOIN account_users au ON au.account_id = a.id
                    SET a.name = :name
                  WHERE au.user_id = :uid AND au.role = \'owner\'',
                ['name' => $sets['business_name'], 'uid' => (int) $row['id']],
            );
        }

        return true;
    }

    /** Marks a member deleted on Toutify. Their content is left alone. */
    public static function markDeleted(string $sub): void
    {
        Database::run(
            "UPDATE users SET deleted_at = NOW(), status = 'suspended' WHERE toutify_sub = :s",
            ['s' => $sub],
        );
    }

    /* ------------------------------------------------------------ websites */

    /**
     * Replaces a member's website list with the one Toutify just gave us.
     *
     * Anything of theirs NOT in the list is marked removed rather than
     * deleted: a website that disappears from Toutify may come back, and the
     * reviews tied to it must not vanish with it.
     *
     * @param array<int,array<string,mixed>> $websites
     */
    public static function syncWebsites(string $owner, array $websites): void
    {
        $seen = [];

        foreach ($websites as $website) {
            if (!is_array($website)) {
                continue;
            }
            $website['owner'] = $website['owner'] ?? $owner;
            $id = self::saveWebsite($website);
            if ($id !== null) {
                $seen[] = $id;
            }
        }

        if ($seen === []) {
            Database::run(
                "UPDATE toutify_websites SET status = 'removed' WHERE owner = :o",
                ['o' => $owner],
            );
            return;
        }

        // Placeholders rather than an interpolated list: the ids come from
        // Toutify, but a query built by concatenation is a habit, not an
        // exception you make once.
        $marks = implode(',', array_fill(0, count($seen), '?'));
        $stmt  = Database::connection()->prepare(
            "UPDATE toutify_websites SET status = 'removed'
              WHERE owner = ? AND id NOT IN ({$marks})"
        );
        $stmt->execute(array_merge([$owner], $seen));
    }

    /**
     * Inserts or updates one website, newest-wins. Returns its id.
     *
     * @param array<string,mixed> $website
     */
    public static function saveWebsite(array $website): ?string
    {
        $id    = trim((string) ($website['id'] ?? ''));
        $owner = trim((string) ($website['owner'] ?? ''));

        if ($id === '' || $owner === '') {
            return null;
        }

        $incoming = self::updatedAt($website);
        $held     = Database::first(
            'SELECT toutify_updated_at FROM toutify_websites WHERE id = :id',
            ['id' => $id],
        );

        if ($held !== null && $held['toutify_updated_at'] !== null && $incoming !== null
            && $incoming < (int) $held['toutify_updated_at']) {
            return $id;
        }

        Database::run(
            'INSERT INTO toutify_websites
                (id, owner, url, business_name, phone, street, city, state, zip, country,
                 tier, status, toutify_updated_at)
             VALUES (:id, :owner, :url, :business, :phone, :street, :city, :state, :zip,
                     :country, :tier, :status, :updated)
             ON DUPLICATE KEY UPDATE
                owner = VALUES(owner), url = VALUES(url),
                business_name = VALUES(business_name), phone = VALUES(phone),
                street = VALUES(street), city = VALUES(city), state = VALUES(state),
                zip = VALUES(zip), country = VALUES(country), tier = VALUES(tier),
                status = VALUES(status), toutify_updated_at = VALUES(toutify_updated_at)',
            [
                'id'       => $id,
                'owner'    => $owner,
                'url'      => (string) ($website['url'] ?? ''),
                'business' => self::nullable($website['business_name'] ?? null),
                'phone'    => self::nullable($website['phone'] ?? null),
                'street'   => self::nullable($website['street'] ?? null),
                'city'     => self::nullable($website['city'] ?? null),
                'state'    => self::nullable($website['state'] ?? null),
                'zip'      => self::nullable($website['zip'] ?? null),
                'country'  => self::nullable($website['country'] ?? null),
                'tier'     => self::nullable($website['tier'] ?? null),
                'status'   => (string) ($website['status'] ?? 'active'),
                'updated'  => $incoming,
            ],
        );

        return $id;
    }

    public static function removeWebsite(string $id): void
    {
        Database::run(
            "UPDATE toutify_websites SET status = 'removed' WHERE id = :id",
            ['id' => $id],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function websitesFor(string $owner): array
    {
        return Database::all(
            "SELECT * FROM toutify_websites
              WHERE owner = :o AND status = 'active'
           ORDER BY business_name IS NULL, business_name, url",
            ['o' => $owner],
        );
    }

    /* -------------------------------------------------------------- helpers */

    /** @param array<string,mixed> $data */
    private static function updatedAt(array $data): ?int
    {
        $value = $data['updated_at'] ?? null;

        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }

    private static function nullable(mixed $value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** @return array{0:string,1:string} */
    private static function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2) ?: [];

        return [$parts[0] ?? $name, $parts[1] ?? ''];
    }
}
