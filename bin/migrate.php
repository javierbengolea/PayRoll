<?php
declare(strict_types=1);

/**
 * Aplica las migraciones pendientes de database/migrations/, en orden alfabético.
 *
 *   php bin/migrate.php            aplica las pendientes
 *   php bin/migrate.php --status   solo muestra el estado
 */

use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    exit("Este script se ejecuta desde la línea de comandos.\n");
}

require dirname(__DIR__) . '/src/bootstrap.php';
require_once __DIR__ . '/sql.php';

$pdo = Database::connection()->pdo();
$pdo->exec('CREATE TABLE IF NOT EXISTS migrations (
    name VARCHAR(190) PRIMARY KEY,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$applied = array_flip($pdo->query('SELECT name FROM migrations')->fetchAll(PDO::FETCH_COLUMN));
$files = glob(BASE_PATH . '/database/migrations/*.sql');
sort($files);
$pending = array_filter($files, fn ($f) => !isset($applied[basename($f)]));

if (in_array('--status', $argv, true)) {
    foreach ($files as $f) {
        echo (isset($applied[basename($f)]) ? '  [aplicada]  ' : '  [pendiente] ') . basename($f) . "\n";
    }
    exit(0);
}

if (!$pending) {
    echo "La base está al día.\n";
    exit(0);
}

foreach ($pending as $file) {
    $name = basename($file);
    echo "→ $name\n";
    try {
        // MySQL confirma implícitamente los CREATE/ALTER, así que no se usa transacción:
        // si una sentencia falla, se informa cuál para poder corregirla a mano.
        run_sql_file($pdo, $file);
        $pdo->prepare('INSERT INTO migrations (name) VALUES (?)')->execute([$name]);
    } catch (Throwable $e) {
        fwrite(STDERR, "\n✘ Falló $name: {$e->getMessage()}\n");
        exit(1);
    }
}
echo "\n✔ " . count($pending) . " migración(es) aplicada(s).\n";
