<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Applies the .sql files in database/migrations, and records what it applied.
 *
 * Written for shared hosting, where migrations get pasted into phpMyAdmin by
 * hand and the tracking table therefore does not exist even though most of the
 * schema does. Three behaviours follow from that:
 *
 *  1. BASELINE. On a database that has no tracking table but does have tables,
 *     every migration whose objects are already present is recorded as applied
 *     WITHOUT being executed. This is not a nicety: migration 006 drops the
 *     panel-era tables, `users` among them, so replaying history against a live
 *     database would delete the logins.
 *
 *  2. PER-STATEMENT. Files are split and executed one statement at a time, so a
 *     half-applied migration finishes instead of stopping at its first clash.
 *     "Already exists" errors are treated as success for that statement only.
 *
 *  3. REFUSE TO DESTROY. A DROP against a table that currently holds rows aborts
 *     the run. A migration that is genuinely meant to drop populated data can be
 *     run by hand; a runner that does it silently cannot be taken back.
 */
final class Migrator
{
    /** Errors that mean "this statement's work is already done". */
    private const ALREADY_DONE = [
        1050, // table exists
        1060, // duplicate column
        1061, // duplicate key name
        1022, // duplicate key
        1826, // duplicate foreign key constraint name
        1091, // can't DROP; doesn't exist
        1359, // trigger already exists
    ];

    /**
     * How to tell, without the tracking table, that a migration has already been
     * applied by hand. Keyed by filename prefix; the first match wins.
     *
     * A migration with no entry here is never baselined — it will be executed.
     *
     * @var array<string,callable(PDO):bool>
     */
    private static function presence(): array
    {
        return [
            '001' => static fn (PDO $p) => self::tableExists($p, 'waitlist'),
            // The teardown is "done" once the panel tables are gone, which is
            // also true of a fresh install. Either way there is nothing to drop.
            '006' => static fn (PDO $p) => !self::tableExists($p, 'task_slots'),
            '007' => static fn (PDO $p) => self::tableExists($p, 'accounts'),
            '008' => static fn (PDO $p) => self::tableExists($p, 'contacts'),
            '009' => static fn (PDO $p) => self::tableExists($p, 'reviews'),
            '010' => static fn (PDO $p) => self::tableExists($p, 'messaging_brands'),
            '011' => static fn (PDO $p) => self::tableExists($p, 'audit_log'),
            '012' => static fn (PDO $p) => str_contains(
                self::columnType($p, 'waitlist', 'role'),
                "'agency'"
            ),
            '013' => static fn (PDO $p) => self::columnExists($p, 'audits', 'status')
                && self::tableExists($p, 'login_attempts'),
            '014' => static fn (PDO $p) => self::columnExists($p, 'users', 'must_change_password'),
        ];
    }

    public static function directory(): string
    {
        return BASE_PATH . '/database/migrations';
    }

    /** @return array<string,string> filename => absolute path, in apply order */
    public static function files(): array
    {
        $paths = glob(self::directory() . '/*.sql') ?: [];
        sort($paths);

        $files = [];
        foreach ($paths as $path) {
            $files[basename($path)] = $path;
        }

        return $files;
    }

    public static function ensureTrackingTable(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                filename   VARCHAR(191) NOT NULL,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (filename)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return array<int,string> */
    public static function applied(PDO $pdo): array
    {
        if (!self::tableExists($pdo, 'migrations')) {
            return [];
        }

        return $pdo->query('SELECT filename FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Works out what a run would do, without changing anything.
     *
     * @return array<int,array{file:string,action:string,reason:string}>
     */
    public static function plan(PDO $pdo): array
    {
        $tracked  = self::applied($pdo);
        $isVirgin = !self::tableExists($pdo, 'users') && !self::tableExists($pdo, 'waitlist');
        $presence = self::presence();

        $plan = [];
        foreach (self::files() as $name => $path) {
            if (in_array($name, $tracked, true)) {
                $plan[] = ['file' => $name, 'action' => 'skip', 'reason' => 'Already recorded as applied.'];
                continue;
            }

            $prefix = substr($name, 0, 3);
            if (!$isVirgin && isset($presence[$prefix]) && ($presence[$prefix])($pdo)) {
                $plan[] = ['file' => $name, 'action' => 'baseline',
                    'reason' => 'Its tables and columns are already here — record it, do not re-run it.'];
                continue;
            }

            $plan[] = ['file' => $name, 'action' => 'apply',
                'reason' => count(self::statements((string) file_get_contents($path))) . ' statement(s) to run.'];
        }

        return $plan;
    }

    /**
     * Applies everything the plan says to apply.
     *
     * @return array<int,array{file:string,state:string,detail:string}>
     */
    public static function run(PDO $pdo): array
    {
        self::ensureTrackingTable($pdo);

        $record = $pdo->prepare('INSERT INTO migrations (filename) VALUES (?)');
        $files  = self::files();
        $log    = [];

        foreach (self::plan($pdo) as $step) {
            $name = $step['file'];

            if ($step['action'] === 'skip') {
                $log[] = ['file' => $name, 'state' => 'skip', 'detail' => $step['reason']];
                continue;
            }

            if ($step['action'] === 'baseline') {
                $record->execute([$name]);
                $log[] = ['file' => $name, 'state' => 'baseline', 'detail' => $step['reason']];
                continue;
            }

            $statements = self::statements((string) file_get_contents($files[$name]));
            $ran = 0;
            $tolerated = 0;

            foreach ($statements as $statement) {
                self::refuseToDestroyData($pdo, $statement);

                try {
                    $pdo->exec($statement);
                    $ran++;
                } catch (PDOException $e) {
                    if (in_array((int) ($e->errorInfo[1] ?? 0), self::ALREADY_DONE, true)) {
                        $tolerated++;
                        continue;
                    }

                    $log[] = ['file' => $name, 'state' => 'fail',
                        'detail' => $e->getMessage() . "\n\nStatement:\n" . $statement];

                    return $log; // Stop: later migrations assume this one landed.
                }
            }

            $record->execute([$name]);
            $log[] = ['file' => $name, 'state' => 'applied',
                'detail' => $tolerated > 0
                    ? "{$ran} statement(s) run, {$tolerated} already in place."
                    : "{$ran} statement(s) run."];
        }

        return $log;
    }

    /**
     * Splits a migration file into statements, discarding comments.
     *
     * A general SQL parser this is not; it understands quoting well enough for
     * the DDL this project ships, which is what it is used on.
     *
     * @return array<int,string>
     */
    public static function statements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = null;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($quote !== null) {
                $buffer .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $buffer .= $sql[++$i];
                } elseif ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            // "--" starts a comment only when followed by whitespace, so that
            // an expression like `a--b` is left alone.
            if ($char === '-' && ($sql[$i + 1] ?? '') === '-'
                && in_array($sql[$i + 2] ?? "\n", [' ', "\t", "\n", "\r"], true)) {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }
                $buffer .= "\n";
                continue;
            }

            if ($char === '#') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }
                $buffer .= "\n";
                continue;
            }

            if ($char === '/' && ($sql[$i + 1] ?? '') === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $i = $end === false ? $length : $end + 1;
                continue;
            }

            if ($char === ';') {
                if (trim($buffer) !== '') {
                    $statements[] = trim($buffer);
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }

        return $statements;
    }

    /**
     * A DROP that would take rows with it stops the run. Dropping an empty
     * leftover table is routine; dropping a populated one is a decision, and not
     * one an automated runner gets to make.
     */
    private static function refuseToDestroyData(PDO $pdo, string $statement): void
    {
        if (!preg_match('/^\s*DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?`?([A-Za-z0-9_]+)`?/i', $statement, $m)) {
            return;
        }

        $table = $m[1];
        if (!self::tableExists($pdo, $table)) {
            return;
        }

        $rows = (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
        if ($rows === 0) {
            return;
        }

        throw new RuntimeException(
            "Refusing to run: this migration drops the table `{$table}`, which currently holds "
            . "{$rows} row(s). Back that data up and drop the table by hand if the drop is "
            . "genuinely intended, then re-run."
        );
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        return self::columnType($pdo, $table, $column) !== '';
    }

    private static function columnType(PDO $pdo, string $table, string $column): string
    {
        $stmt = $pdo->prepare(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);
        $type = $stmt->fetchColumn();

        return $type === false ? '' : (string) $type;
    }
}
