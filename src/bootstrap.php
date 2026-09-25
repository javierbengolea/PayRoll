<?php
declare(strict_types=1);

use App\Core\Config;

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = BASE_PATH . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require_once BASE_PATH . '/src/helpers.php';

$configFile = is_file(BASE_PATH . '/config/config.local.php')
    ? BASE_PATH . '/config/config.local.php'
    : BASE_PATH . '/config/config.example.php';
Config::load(require $configFile);

date_default_timezone_set(Config::get('app.timezone', 'America/Argentina/Buenos_Aires'));

$isDev = Config::get('app.env') === 'development';
error_reporting(E_ALL);
ini_set('display_errors', $isDev ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');
