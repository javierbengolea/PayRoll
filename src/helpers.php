<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;

/** Escapa HTML. Usar SIEMPRE al imprimir datos en las vistas. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Genera una URL interna: url('employees/edit', ['id' => 3]). */
function url(string $route = '', array $params = []): string
{
    $base = rtrim((string) Config::get('app.base_url', ''), '/');
    $query = $route !== '' ? ['r' => $route] + $params : $params;
    return $base . '/index.php' . ($query ? '?' . http_build_query($query) : '');
}

function asset(string $path): string
{
    $base = rtrim((string) Config::get('app.base_url', ''), '/');
    $file = dirname(__DIR__) . '/public/assets/' . ltrim($path, '/');
    $v = is_file($file) ? '?v=' . filemtime($file) : '';
    return $base . '/assets/' . ltrim($path, '/') . $v;
}

function csrf_field(): string
{
    return Csrf::field();
}

function money(mixed $amount, bool $symbol = true): string
{
    $formatted = number_format((float) $amount, 2, ',', '.');
    return $symbol ? '$ ' . $formatted : $formatted;
}

function num(mixed $value, int $decimals = 2): string
{
    if ($value === null || $value === '') {
        return '';
    }
    $formatted = number_format((float) $value, $decimals, ',', '.');
    // Quita ceros decimales innecesarios: 8,3300 -> 8,33 ; 11,00 -> 11
    return str_contains($formatted, ',') ? rtrim(rtrim($formatted, '0'), ',') : $formatted;
}

function fmt_date(?string $date): string
{
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    return $ts ? date('d/m/Y', $ts) : '';
}

function fmt_datetime(?string $date): string
{
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    return $ts ? date('d/m/Y H:i', $ts) : '';
}

function fmt_cuil(?string $cuil): string
{
    $cuil = preg_replace('/\D/', '', (string) $cuil);
    return strlen($cuil) === 11 ? substr($cuil, 0, 2) . '-' . substr($cuil, 2, 8) . '-' . substr($cuil, 10) : (string) $cuil;
}

function month_name(int $month): string
{
    static $months = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio',
        'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    return $months[$month] ?? '';
}

function period_label(array $period): string
{
    if (($period['type'] ?? 'mensual') === 'sac') {
        return 'SAC ' . ((int) $period['month'] <= 6 ? '1er' : '2do') . ' semestre ' . $period['year'];
    }
    return month_name((int) $period['month']) . ' ' . $period['year'];
}

/** Valor anterior de un campo (tras error de validación) o el valor por defecto. */
function old(string $key, mixed $default = ''): mixed
{
    global $__old;
    if (!empty($__old['input'])) {
        // Tras un envío fallido, un campo ausente es un checkbox desmarcado
        return $__old['input'][$key] ?? '';
    }
    return $default;
}

function field_error(string $key): string
{
    global $__old;
    $msg = $__old['errors'][$key] ?? null;
    return $msg ? '<div class="invalid-feedback d-block">' . e($msg) . '</div>' : '';
}

function invalid(string $key): string
{
    global $__old;
    return isset($__old['errors'][$key]) ? ' is-invalid' : '';
}

function can(string $role): bool
{
    return Auth::can($role);
}

function selected(mixed $a, mixed $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function checked(mixed $value): string
{
    return $value ? ' checked' : '';
}

const CONCEPT_TYPES = [
    'haber_rem'    => 'Haber remunerativo',
    'haber_no_rem' => 'Haber no remunerativo',
    'descuento'    => 'Descuento',
    'contribucion' => 'Contribución patronal',
];

const CALC_MODES = [
    'basico'     => 'Sueldo básico',
    'fijo'       => 'Importe fijo',
    'porcentaje' => 'Porcentaje sobre base',
    'cantidad'   => 'Cantidad × valor unitario',
    'horas'      => 'Horas (valor hora × %)',
    'dias'       => 'Días (básico/30 × %)',
    'antiguedad' => 'Antigüedad (% por año)',
    'sac'        => 'SAC / aguinaldo',
];

const CONCEPT_BASES = [
    'basico'          => 'Sueldo básico',
    'remunerativo'    => 'Total remunerativo',
    'no_remunerativo' => 'Total no remunerativo',
    'bruto'           => 'Total bruto',
];

const CONTRACT_TYPES = [
    'permanente' => 'Permanente',
    'plazo_fijo' => 'Plazo fijo',
    'eventual'   => 'Eventual',
    'pasantia'   => 'Pasantía',
];

const EMPLOYEE_STATUS = [
    'activo'   => ['Activo', 'success'],
    'licencia' => ['Licencia', 'warning'],
    'baja'     => ['Baja', 'secondary'],
];

const PERIOD_STATUS = [
    'borrador'  => ['Borrador', 'secondary'],
    'calculada' => ['Calculada', 'info'],
    'cerrada'   => ['Cerrada', 'success'],
];

const ROLES = [
    'admin'    => 'Administrador',
    'rrhh'     => 'Recursos Humanos',
    'consulta' => 'Solo consulta',
];
