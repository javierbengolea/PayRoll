<?php
declare(strict_types=1);

namespace App\Core;

final class Audit
{
    public static function log(string $action, string $entity, ?int $entityId = null, ?string $details = null, ?int $userId = null): void
    {
        try {
            Database::connection()->insert('audit_log', [
                'user_id'   => $userId ?? Auth::id(),
                'action'    => $action,
                'entity'    => $entity,
                'entity_id' => $entityId,
                'details'   => $details !== null ? mb_substr($details, 0, 500) : null,
                'ip'        => $_SERVER['REMOTE_ADDR'] ?? 'cli',
            ]);
        } catch (\Throwable $e) {
            error_log('Audit error: ' . $e->getMessage());
        }
    }
}
