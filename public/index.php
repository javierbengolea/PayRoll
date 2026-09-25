<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;

require dirname(__DIR__) . '/src/bootstrap.php';

// Cabeceras de seguridad
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

Session::start();

$routes = require BASE_PATH . '/src/routes.php';
$route = trim((string) ($_GET['r'] ?? ''), '/');
if ($route === '') {
    $route = Auth::check() ? 'dashboard' : 'login';
}

try {
    if (!isset($routes[$route])) {
        http_response_code(404);
        View::render('errors/404');
        exit;
    }

    [$controller, $action, $method, $role] = $routes[$route];

    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        http_response_code(405);
        header('Allow: ' . $method);
        exit('Método no permitido');
    }

    if ($method === 'POST' && !Csrf::verify($_POST['_csrf'] ?? null)) {
        http_response_code(419);
        Session::flash('danger', 'La sesión expiró o el formulario no es válido. Volvé a intentarlo.');
        header('Location: ' . url(Auth::check() ? 'dashboard' : 'login'));
        exit;
    }

    if ($role !== null) {
        if (!Auth::check()) {
            header('Location: ' . url('login'));
            exit;
        }
        if (!Auth::can($role)) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }
    }

    (new $controller())->$action();
} catch (PDOException $e) {
    error_log((string) $e);
    http_response_code(500);
    View::render('errors/500', ['message' => 'No se pudo acceder a la base de datos. Revisá la configuración en config/config.local.php.', 'exception' => $e], null);
} catch (Throwable $e) {
    error_log((string) $e);
    http_response_code(500);
    View::render('errors/500', ['message' => 'Ocurrió un error inesperado.', 'exception' => $e], null);
}
