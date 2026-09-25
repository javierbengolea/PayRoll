<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Validador simple con reglas encadenadas por "|".
 *   required, email, numeric, integer, min:n, max:n, date, in:a,b,c, cuil, cbu, maxlen:n
 */
final class Validator
{
    private array $errors = [];

    public function __construct(private array $data)
    {
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        $v = new self($data);
        foreach ($rules as $field => $ruleString) {
            $v->check($field, explode('|', $ruleString), $labels[$field] ?? $field);
        }
        return $v;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    private function check(string $field, array $rules, string $label): void
    {
        $value = $this->data[$field] ?? null;
        $value = is_string($value) ? trim($value) : $value;
        $empty = $value === null || $value === '';

        if (in_array('required', $rules, true) && $empty) {
            $this->addError($field, "$label es obligatorio.");
            return;
        }
        if ($empty) {
            return;
        }

        foreach ($rules as $rule) {
            [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
            $error = match ($name) {
                'email'   => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : "$label no es un email válido.",
                'numeric' => is_numeric(self::normalizeNumber((string) $value)) ? null : "$label debe ser un número.",
                'integer' => preg_match('/^-?\d+$/', (string) $value) ? null : "$label debe ser un número entero.",
                'min'     => (float) self::normalizeNumber((string) $value) >= (float) $arg ? null : "$label debe ser mayor o igual a $arg.",
                'max'     => (float) self::normalizeNumber((string) $value) <= (float) $arg ? null : "$label debe ser menor o igual a $arg.",
                'maxlen'  => mb_strlen((string) $value) <= (int) $arg ? null : "$label admite hasta $arg caracteres.",
                'date'    => self::isDate((string) $value) ? null : "$label no es una fecha válida.",
                'in'      => in_array((string) $value, explode(',', (string) $arg), true) ? null : "$label tiene un valor no permitido.",
                'cuil'    => self::isCuil((string) $value) ? null : "$label no es un CUIL válido.",
                'cbu'     => self::isCbu((string) $value) ? null : "$label no es un CBU válido.",
                default   => null,
            };
            if ($error !== null) {
                $this->addError($field, $error);
                return;
            }
        }
    }

    /**
     * Normaliza importes escritos al estilo argentino o internacional:
     *   "1.234,56" -> "1234.56"   "1.100.000" -> "1100000"   "1.500" -> "1500"
     *   "1234.56"  -> "1234.56"   "8,33"      -> "8.33"      "0.125" -> "0.125"
     */
    public static function normalizeNumber(string $value): string
    {
        $value = str_replace([' ', '$', "\u{a0}"], '', trim($value));
        if (str_contains($value, ',')) {
            return str_replace(',', '.', str_replace('.', '', $value));
        }
        // Solo puntos: si hay más de uno, o uno seguido de exactamente 3 dígitos
        // (y la parte entera no es 0), son separadores de miles.
        if (substr_count($value, '.') > 1 || preg_match('/^-?[1-9]\d{0,2}\.\d{3}$/', $value)) {
            return str_replace('.', '', $value);
        }
        return $value;
    }

    public static function isDate(string $value): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $value);
        return $d !== false && $d->format('Y-m-d') === $value;
    }

    /** Valida CUIL/CUIT con su dígito verificador (módulo 11). */
    public static function isCuil(string $value): bool
    {
        $digits = preg_replace('/\D/', '', $value);
        if (strlen($digits) !== 11) {
            return false;
        }
        if (!in_array(substr($digits, 0, 2), ['20', '23', '24', '27', '30', '33', '34'], true)) {
            return false;
        }
        return self::cuilCheckDigit(substr($digits, 0, 10)) === (int) $digits[10];
    }

    public static function cuilCheckDigit(string $first10): int
    {
        $weights = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        foreach ($weights as $i => $w) {
            $sum += (int) $first10[$i] * $w;
        }
        $check = 11 - ($sum % 11);
        return match ($check) {
            11 => 0,
            10 => 9,
            default => $check,
        };
    }

    /** Valida CBU (22 dígitos, dos bloques con dígito verificador). */
    public static function isCbu(string $value): bool
    {
        $cbu = preg_replace('/\D/', '', $value);
        if (strlen($cbu) !== 22) {
            return false;
        }
        $check = static function (string $block, array $weights): bool {
            $body = substr($block, 0, -1);
            $sum = 0;
            foreach (str_split($body) as $i => $d) {
                $sum += (int) $d * $weights[$i];
            }
            return (10 - ($sum % 10)) % 10 === (int) substr($block, -1);
        };
        return $check(substr($cbu, 0, 8), [7, 1, 3, 9, 7, 1, 3])
            && $check(substr($cbu, 8), [3, 9, 7, 1, 3, 9, 7, 1, 3, 9, 7, 1, 3]);
    }
}
