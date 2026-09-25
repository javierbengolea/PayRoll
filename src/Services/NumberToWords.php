<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Convierte importes a letras en español, para el recibo de sueldo.
 *   NumberToWords::money(1234.5) => "PESOS MIL DOSCIENTOS TREINTA Y CUATRO CON 50/100"
 */
final class NumberToWords
{
    private const UNITS = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve',
        'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve',
        'veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis',
        'veintisiete', 'veintiocho', 'veintinueve'];
    private const TENS = ['', '', '', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
    private const HUNDREDS = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos',
        'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];

    public static function money(float $amount): string
    {
        $amount = round(abs($amount), 2);
        $integer = (int) floor($amount);
        $cents = (int) round(($amount - $integer) * 100);
        $words = $integer === 0 ? 'cero' : self::convert($integer);
        return mb_strtoupper(sprintf('pesos %s con %02d/100', $words, $cents));
    }

    public static function convert(int $n): string
    {
        if ($n === 0) {
            return 'cero';
        }
        $parts = [];
        $millions = intdiv($n, 1_000_000);
        $thousands = intdiv($n % 1_000_000, 1000);
        $rest = $n % 1000;

        if ($millions > 0) {
            $parts[] = $millions === 1 ? 'un millón' : self::apocope(self::convert($millions)) . ' millones';
        }
        if ($thousands > 0) {
            $parts[] = $thousands === 1 ? 'mil' : self::apocope(self::hundreds($thousands)) . ' mil';
        }
        if ($rest > 0) {
            $parts[] = self::hundreds($rest);
        }
        return implode(' ', $parts);
    }

    private static function hundreds(int $n): string
    {
        if ($n === 100) {
            return 'cien';
        }
        $h = intdiv($n, 100);
        $r = $n % 100;
        $words = self::HUNDREDS[$h];
        if ($r > 0) {
            $words .= ($words !== '' ? ' ' : '') . self::tens($r);
        }
        return $words;
    }

    private static function tens(int $n): string
    {
        if ($n < 30) {
            return self::UNITS[$n];
        }
        $t = intdiv($n, 10);
        $u = $n % 10;
        return self::TENS[$t] . ($u > 0 ? ' y ' . self::UNITS[$u] : '');
    }

    /** "veintiuno mil" -> "veintiún mil", "uno" -> "un" delante de mil/millones. */
    private static function apocope(string $words): string
    {
        if (str_ends_with($words, 'veintiuno')) {
            return substr($words, 0, -strlen('veintiuno')) . 'veintiún';
        }
        if (str_ends_with($words, 'uno')) {
            return substr($words, 0, -3) . 'un';
        }
        return $words;
    }
}
