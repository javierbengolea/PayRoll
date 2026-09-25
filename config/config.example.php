<?php
/**
 * Copiá este archivo como config/config.local.php y ajustá los valores.
 * También podés usar variables de entorno (DB_HOST, DB_NAME, ...).
 */
return [
    'app' => [
        'name'     => 'PayRoll',
        'env'      => getenv('APP_ENV') ?: 'production',   // 'development' muestra errores
        'timezone' => 'America/Argentina/Cordoba',
        'base_url' => getenv('APP_BASE_URL') ?: '',        // ej: '/payroll/public'
    ],
    'db' => [
        'host'     => getenv('DB_HOST') ?: '127.0.0.1',
        'port'     => (int) (getenv('DB_PORT') ?: 3306),
        'name'     => getenv('DB_NAME') ?: 'payroll',
        'user'     => getenv('DB_USER') ?: 'payroll',
        'password' => getenv('DB_PASSWORD') ?: '',
    ],
    'security' => [
        'max_login_attempts' => 5,
        'lockout_minutes'    => 15,
        'session_lifetime'   => 7200,
    ],
];
