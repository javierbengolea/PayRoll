<?php
declare(strict_types=1);

/**
 * Instalador por línea de comandos.
 *
 *   php bin/install.php --admin-email=admin@empresa.com --admin-password=secreto123 [--demo] [--force]
 *
 *   --demo   Carga empleados, departamentos y liquidaciones de ejemplo.
 *   --force  Borra y recrea las tablas si ya existen (¡se pierden los datos!).
 */

use App\Core\Config;
use App\Core\Database;
use App\Services\PayrollService;

if (PHP_SAPI !== 'cli') {
    exit("Este script se ejecuta desde la línea de comandos.\n");
}

require dirname(__DIR__) . '/src/bootstrap.php';
require_once __DIR__ . '/sql.php';

$opts = getopt('', ['admin-email:', 'admin-password:', 'admin-name:', 'demo', 'force']);
$email = strtolower(trim($opts['admin-email'] ?? 'admin@payroll.local'));
$password = $opts['admin-password'] ?? null;
$name = $opts['admin-name'] ?? 'Administrador';

if ($password === null) {
    $password = bin2hex(random_bytes(6));
    $generated = true;
}
if (strlen($password) < 8) {
    fwrite(STDERR, "La contraseña debe tener al menos 8 caracteres.\n");
    exit(1);
}

$host = Config::get('db.host');
$port = (int) Config::get('db.port', 3306);
$dbName = (string) Config::get('db.name');
if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
    fwrite(STDERR, "Nombre de base inválido: $dbName\n");
    exit(1);
}

step("Conectando a MySQL en $host:$port");
$pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", (string) Config::get('db.user'), (string) Config::get('db.password'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `$dbName`");

$exists = (bool) $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
if ($exists && !isset($opts['force'])) {
    fwrite(STDERR, "La base `$dbName` ya tiene tablas. Usá --force para recrearla (se borran los datos).\n");
    exit(1);
}

step('Creando tablas');
run_sql_file($pdo, BASE_PATH . '/database/schema.sql');
// schema.sql ya refleja todas las migraciones: se marcan como aplicadas
$mark = $pdo->prepare('INSERT INTO migrations (name) VALUES (?)');
foreach (glob(BASE_PATH . '/database/migrations/*.sql') as $migration) {
    $mark->execute([basename($migration)]);
}
step('Cargando configuración y conceptos estándar');
run_sql_file($pdo, BASE_PATH . '/database/seed.sql');

$db = new Database($pdo);
Database::setConnection($db);

step("Creando usuario administrador $email");
$adminId = $db->insert('users', [
    'name' => $name,
    'email' => $email,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'role' => 'admin',
]);

if (isset($opts['demo'])) {
    step('Cargando datos de demostración');
    loadDemo($db, $adminId);
}

echo "\n\033[32m✔ Instalación completa.\033[0m\n";
echo "  Usuario:    $email\n";
echo '  Contraseña: ' . $password . (isset($generated) ? '  (generada al azar: guardala)' : '') . "\n\n";

// ---------------------------------------------------------------------

function step(string $msg): void
{
    echo "→ $msg\n";
}

function cuil(string $prefix, int $dni): string
{
    $base = $prefix . str_pad((string) $dni, 8, '0', STR_PAD_LEFT);
    $check = \App\Core\Validator::cuilCheckDigit($base);
    return $base . $check;
}

function cbu(int $seed): string
{
    $digit = static function (string $body, array $w): int {
        $s = 0;
        foreach (str_split($body) as $i => $d) {
            $s += (int) $d * $w[$i];
        }
        return (10 - $s % 10) % 10;
    };
    $b1 = '0110' . str_pad((string) (100 + $seed % 900), 3, '0', STR_PAD_LEFT);
    $b1 .= $digit($b1, [7, 1, 3, 9, 7, 1, 3]);
    $b2 = str_pad((string) (3000000000000 + $seed * 7919), 13, '0', STR_PAD_LEFT);
    $b2 .= $digit($b2, [3, 9, 7, 1, 3, 9, 7, 1, 3, 9, 7, 1, 3]);
    return $b1 . $b2;
}

function loadDemo(Database $db, int $adminId): void
{
    mt_srand(2024);
    $today = new DateTimeImmutable('first day of this month');

    // Convenios, categorías y escalas: una inicial, una paritaria a mitad de año,
    // otra vigente desde este mes y una programada para el mes próximo.
    $agreements = [
        ['130/75', 'Empleados de Comercio', [
            ['MAE-A', 'Maestranza A', 905000], ['MAE-C', 'Maestranza C', 932000],
            ['ADM-A', 'Administrativo A', 912000], ['ADM-B', 'Administrativo B', 921000],
            ['ADM-D', 'Administrativo D', 948000], ['ADM-E', 'Administrativo E', 972000],
            ['VEN-B', 'Vendedor B', 940000], ['VEN-D', 'Vendedor D', 985000],
        ]],
        ['FC', 'Fuera de convenio', [
            ['PRO', 'Profesional', 1500000], ['PSR', 'Profesional senior', 2000000],
            ['JEF', 'Jefatura', 1450000], ['GER', 'Gerencia', 1900000],
        ]],
    ];
    $scales = [
        [$today->modify('-8 months')->format('Y-m-d'), 1.0],
        [$today->modify('-4 months')->format('Y-m-d'), 1.06],
        [$today->format('Y-m-d'), 1.06 * 1.045],
        [$today->modify('+1 month')->format('Y-m-d'), 1.06 * 1.045 * 1.03],
    ];
    $categoryIds = [];
    foreach ($agreements as [$code, $name, $cats]) {
        $agreementId = $db->insert('agreements', ['code' => $code, 'name' => $name]);
        foreach ($cats as $k => [$catCode, $catName, $salary]) {
            $catId = $db->insert('categories', ['agreement_id' => $agreementId, 'code' => $catCode, 'name' => $catName, 'sort_order' => ($k + 1) * 10]);
            $categoryIds[$catCode] = ['id' => $catId, 'salary' => $salary];
            foreach ($scales as [$from, $factor]) {
                $db->insert('category_salaries', ['category_id' => $catId, 'valid_from' => $from, 'base_salary' => round($salary * $factor, -2)]);
            }
        }
    }

    // Departamentos y puestos, cada puesto con su categoría habitual
    $deps = [
        'Administración'   => [['Jefe administrativo', 'JEF'], ['Analista contable', 'ADM-E'], ['Auxiliar administrativo', 'ADM-B']],
        'Ventas'           => [['Gerente comercial', 'GER'], ['Ejecutivo de cuentas', 'VEN-D'], ['Vendedor', 'VEN-B']],
        'Producción'       => [['Supervisor de planta', 'JEF'], ['Operario calificado', 'MAE-C'], ['Operario', 'MAE-A']],
        'Sistemas'         => [['Líder técnico', 'PSR'], ['Desarrollador', 'PRO'], ['Soporte técnico', 'ADM-D']],
        'Recursos Humanos' => [['Responsable de RRHH', 'JEF'], ['Analista de RRHH', 'ADM-E']],
    ];
    $positions = [];
    foreach ($deps as $depName => $posList) {
        $depId = $db->insert('departments', ['name' => $depName]);
        foreach ($posList as [$posName, $catCode]) {
            $positions[] = [
                'id'       => $db->insert('positions', ['department_id' => $depId, 'name' => $posName]),
                'dep'      => $depId,
                'category' => $categoryIds[$catCode]['id'],
                'salary'   => $categoryIds[$catCode]['salary'],
            ];
        }
    }

    $first = ['Lucía', 'Martín', 'Sofía', 'Juan', 'Valentina', 'Mateo', 'Camila', 'Santiago', 'Martina', 'Tomás',
        'Julieta', 'Nicolás', 'Florencia', 'Facundo', 'Agustina', 'Joaquín', 'Paula', 'Federico', 'Micaela', 'Diego',
        'Carolina', 'Gonzalo', 'Rocío', 'Emiliano'];
    $last = ['González', 'Rodríguez', 'Fernández', 'López', 'Martínez', 'Pérez', 'Gómez', 'Díaz', 'Sánchez', 'Romero',
        'Sosa', 'Álvarez', 'Torres', 'Ruiz', 'Ramírez', 'Flores', 'Acosta', 'Benítez', 'Medina', 'Herrera',
        'Aguirre', 'Pereyra', 'Gutiérrez', 'Giménez'];
    $banks = ['Banco Nación', 'Banco Galicia', 'Banco Santander', 'Banco Macro', 'BBVA'];

    $employeeIds = [];
    foreach ($first as $i => $fn) {
        $pos = $positions[$i % count($positions)];
        $female = in_array($fn, ['Lucía', 'Sofía', 'Valentina', 'Camila', 'Martina', 'Julieta', 'Florencia', 'Agustina', 'Paula', 'Micaela', 'Carolina', 'Rocío'], true);
        $dni = 25000000 + $i * 731003 % 20000000;
        $hire = $today->modify('-' . mt_rand(2, 150) . ' months')->modify('+' . mt_rand(0, 27) . ' days');
        $birth = (new DateTimeImmutable('1970-01-01'))->modify('+' . mt_rand(0, 12000) . ' days');
        $employeeIds[] = $db->insert('employees', [
            'file_number'   => (string) (1001 + $i),
            'first_name'    => $fn,
            'last_name'     => $last[$i],
            'cuil'          => cuil($female ? '27' : '20', $dni),
            'birth_date'    => $birth->format('Y-m-d'),
            'gender'        => $female ? 'F' : 'M',
            'nationality'   => 'Argentina',
            'email'         => strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $fn . '.' . $last[$i])) . '@ejemplo.com',
            'phone'         => '351-' . mt_rand(4000000, 6999999),
            'address'       => 'Calle ' . mt_rand(1, 99) . ' N° ' . mt_rand(100, 3999) . ', Córdoba',
            'hire_date'     => $hire->format('Y-m-d'),
            'department_id' => $pos['dep'],
            'position_id'   => $pos['id'],
            'category_id'   => $pos['category'],
            'contract_type' => $i % 9 === 8 ? 'plazo_fijo' : 'permanente',
            'base_salary'   => $i % 12 === 7 ? round($pos['salary'] * 1.25, -3) : null, // alguno con básico propio
            'bank_name'     => $banks[$i % count($banks)],
            'cbu'           => cbu($i + 1),
            'status'        => $i === 5 ? 'licencia' : 'activo',
        ]);
    }

    // Afiliados al sindicato
    $union = (int) $db->value("SELECT id FROM concepts WHERE code = '530'");
    foreach (array_slice($employeeIds, 0, 10) as $id) {
        $db->insert('employee_concepts', ['employee_id' => $id, 'concept_id' => $union]);
    }

    $concept = fn (string $code) => (int) $db->value('SELECT id FROM concepts WHERE code = ?', [$code]);

    // Adicional por título (fórmula BASICO * VALOR / 100), uno con un % propio
    foreach ([1, 10, 13, 16, 23] as $k => $idx) {
        $db->insert('employee_concepts', [
            'employee_id' => $employeeIds[$idx], 'concept_id' => $concept('160'), 'value_override' => $k === 0 ? 15 : null,
        ]);
    }
    $service = new PayrollService($db);

    // Liquidaciones de los últimos 8 meses cerradas + mes actual en borrador
    for ($back = 8; $back >= 0; $back--) {
        $month = $today->modify("-$back months");
        $y = (int) $month->format('Y');
        $m = (int) $month->format('n');
        $periodId = $db->insert('payroll_periods', [
            'year' => $y, 'month' => $m, 'type' => 'mensual',
            'description' => 'Sueldos ' . month_name($m) . " $y",
            'payment_date' => $month->modify('last day of this month')->modify('+4 days')->format('Y-m-d'),
            'created_by' => $adminId,
        ]);
        foreach ($employeeIds as $k => $empId) {
            if (mt_rand(1, 4) === 1) {
                $db->insert('novelties', ['period_id' => $periodId, 'employee_id' => $empId, 'concept_id' => $concept('130'), 'quantity' => mt_rand(2, 16)]);
            }
            if (mt_rand(1, 12) === 1) {
                $db->insert('novelties', ['period_id' => $periodId, 'employee_id' => $empId, 'concept_id' => $concept('140'), 'quantity' => mt_rand(1, 2), 'note' => 'Sin justificar']);
            }
            if ($k % 7 === 0) {
                $db->insert('novelties', ['period_id' => $periodId, 'employee_id' => $empId, 'concept_id' => $concept('300'), 'amount' => 45000, 'note' => 'Acuerdo paritario']);
            }
        }
        if ($back > 0) {
            $service->calculate($periodId);
            $service->close($periodId);
        }

        // SAC al terminar junio o diciembre
        if (($m === 6 || $m === 12) && $back > 0) {
            $sacId = $db->insert('payroll_periods', [
                'year' => $y, 'month' => $m, 'type' => 'sac',
                'description' => 'SAC ' . ($m === 6 ? '1er' : '2do') . " semestre $y",
                'payment_date' => $month->modify('last day of this month')->format('Y-m-d'),
                'created_by' => $adminId,
            ]);
            $service->calculate($sacId);
            $service->close($sacId);
        }
    }
}
