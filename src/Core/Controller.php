<?php
declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected Database $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    protected function render(string $view, array $data = []): void
    {
        View::render($view, $data);
    }

    protected function redirect(string $route, array $params = []): never
    {
        header('Location: ' . url($route, $params));
        exit;
    }

    protected function back(string $fallbackRoute = 'dashboard'): never
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($ref !== '' && parse_url($ref, PHP_URL_HOST) === parse_url('http://' . $host, PHP_URL_HOST)) {
            header('Location: ' . $ref);
            exit;
        }
        $this->redirect($fallbackRoute);
    }

    protected function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function flash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }

    protected function input(string $key, mixed $default = null): mixed
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    protected function intParam(string $key): int
    {
        return (int) ($_GET[$key] ?? $_POST[$key] ?? 0);
    }

    protected function requireRole(string $role): void
    {
        if (!Auth::can($role)) {
            http_response_code(403);
            $this->render('errors/403');
            exit;
        }
    }

    protected function notFound(): never
    {
        http_response_code(404);
        $this->render('errors/404');
        exit;
    }

    /** Si la validación falla, vuelve al formulario con los errores y los datos cargados. */
    protected function failValidation(array $errors, string $route, array $params = []): never
    {
        Session::flashInput($_POST, $errors);
        Session::flash('danger', 'Revisá los datos del formulario.');
        $this->redirect($route, $params);
    }
}
