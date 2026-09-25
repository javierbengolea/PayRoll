<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    /** Jerarquía de roles: cada rol incluye los permisos de los anteriores. */
    private const LEVELS = ['consulta' => 1, 'rrhh' => 2, 'admin' => 3];

    private static ?array $user = null;

    public static function user(): ?array
    {
        if (self::$user === null && ($id = Session::get('user_id'))) {
            self::$user = Database::connection()->one(
                'SELECT id, name, email, role FROM users WHERE id = ? AND active = 1',
                [$id]
            );
            if (self::$user === null) {
                Session::forget('user_id');
            }
        }
        return self::$user;
    }

    public static function id(): ?int
    {
        return isset(self::user()['id']) ? (int) self::user()['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function can(string $role): bool
    {
        $user = self::user();
        return $user !== null && (self::LEVELS[$user['role']] ?? 0) >= (self::LEVELS[$role] ?? 99);
    }

    /**
     * Intenta iniciar sesión. Devuelve null si fue exitoso o el mensaje de error.
     */
    public static function attempt(string $email, string $password): ?string
    {
        $db = Database::connection();
        $user = $db->one('SELECT * FROM users WHERE email = ?', [mb_strtolower(trim($email))]);

        // Mensaje genérico para no revelar qué usuarios existen
        $generic = 'Email o contraseña incorrectos.';
        if ($user === null) {
            password_verify($password, '$2y$12$kMxlCx3McgA44Yxg0qqfCOjHSae5IqLD/s55ETqmpQB3K6nqxgu6S'); // tiempo constante
            return $generic;
        }
        if (!$user['active']) {
            password_verify($password, $user['password_hash']);
            return $generic;
        }
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            return 'Demasiados intentos fallidos. Probá de nuevo más tarde.';
        }
        if (!password_verify($password, $user['password_hash'])) {
            $fails = (int) $user['failed_logins'] + 1;
            $max = (int) Config::get('security.max_login_attempts', 5);
            $lock = $fails >= $max
                ? date('Y-m-d H:i:s', time() + 60 * (int) Config::get('security.lockout_minutes', 15))
                : null;
            $db->update('users', ['failed_logins' => $lock ? 0 : $fails, 'locked_until' => $lock], 'id = :id', ['id' => $user['id']]);
            Audit::log('login_fallido', 'user', (int) $user['id'], null, (int) $user['id']);
            return $generic;
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $db->update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => $user['id']]);
        }
        $db->update('users', [
            'failed_logins' => 0,
            'locked_until'  => null,
            'last_login_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $user['id']]);

        session_regenerate_id(true);
        Session::set('user_id', (int) $user['id']);
        self::$user = null;
        Audit::log('login', 'user', (int) $user['id']);
        return null;
    }

    public static function logout(): void
    {
        Audit::log('logout', 'user', self::id());
        $_SESSION = [];
        session_regenerate_id(true);
        self::$user = null;
    }
}
