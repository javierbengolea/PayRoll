<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class Settings
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            $rows = Database::connection()->all('SELECT skey, svalue FROM settings');
            self::$cache = array_column($rows, 'svalue', 'skey');
        }
        return self::$cache;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::all()[$key] ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        Database::connection()->run(
            'INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)',
            [$key, $value]
        );
        self::$cache = null;
    }
}
