<?php
declare(strict_types=1);

namespace App\Services;

final class CsvExporter
{
    /** Envía un CSV compatible con Excel (UTF-8 con BOM, separador ";"). */
    public static function download(string $filename, array $headers, array $rows): never
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers, ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($out, array_map([self::class, 'sanitize'], $row), ';', '"', '');
        }
        fclose($out);
        exit;
    }

    /** Evita inyección de fórmulas al abrir el archivo en una planilla de cálculo. */
    private static function sanitize(mixed $value): string
    {
        $value = (string) ($value ?? '');
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric(str_replace([',', '.'], '', $value))
            ? "'" . $value
            : $value;
    }
}
