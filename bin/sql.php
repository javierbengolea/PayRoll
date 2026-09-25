<?php
declare(strict_types=1);

/** Ejecuta un archivo .sql sentencia por sentencia (separadas por ";" al final de línea). */
function run_sql_file(PDO $pdo, string $file): void
{
    $sql = (string) file_get_contents($file);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (preg_split('/;\s*(\r?\n|$)/', $sql) as $statement) {
        if (trim($statement) !== '') {
            $pdo->exec($statement);
        }
    }
}
