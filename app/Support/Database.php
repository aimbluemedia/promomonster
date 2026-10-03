<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            Config::get('db.host', 'localhost'),
            (int) Config::get('db.port', 3306),
            Config::get('db.database', ''),
            Config::get('db.charset', 'utf8mb4'),
        );

        self::$pdo = new PDO(
            $dsn,
            (string) Config::get('db.username', ''),
            (string) Config::get('db.password', ''),
            [
                // Throw on error rather than returning false, so a broken query
                // can never be mistaken for an empty result.
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Real prepared statements, not driver-side emulation.
                PDO::ATTR_EMULATE_PREPARES   => false,
            ],
        );

        return self::$pdo;
    }

    /** @param array<string|int,mixed> $params */
    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($params);
        return $statement;
    }

    /** @param array<string|int,mixed> $params @return array<int,array<string,mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** @param array<string|int,mixed> $params @return array<string,mixed>|null */
    public static function first(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function isAvailable(): bool
    {
        try {
            self::connection();
            return true;
        } catch (PDOException) {
            return false;
        }
    }
}
