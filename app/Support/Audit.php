<?php

declare(strict_types=1);

namespace App\Support;

use PDOException;

/**
 * Append-only record of admin actions. Never let a logging failure break the
 * action being logged.
 */
final class Audit
{
    /** @param array<string,mixed>|null $before @param array<string,mixed>|null $after */
    public static function log(
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        ?array $before = null,
        ?array $after = null,
    ): void {
        try {
            $actor = $_SESSION['admin_user_id'] ?? null;
            Database::run(
                'INSERT INTO audit_log (actor_user_id, action, target_type, target_id, before_state, after_state, ip)
                 VALUES (:actor, :action, :target_type, :target_id, :before, :after, :ip)',
                [
                    'actor'       => is_numeric($actor) ? (int) $actor : null,
                    'action'      => $action,
                    'target_type' => $targetType,
                    'target_id'   => $targetId,
                    'before'      => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
                    'after'       => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR),
                    'ip'          => Request::ip(),
                ],
            );
        } catch (PDOException | \JsonException $e) {
            error_log('audit_log: ' . $e->getMessage());
        }
    }
}
