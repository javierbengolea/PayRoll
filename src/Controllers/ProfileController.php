<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;

final class ProfileController extends Controller
{
    public function index(): void
    {
        $this->render('profile/index', ['user' => Auth::user()]);
    }

    public function password(): void
    {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        $hash = (string) $this->db->value('SELECT password_hash FROM users WHERE id = ?', [Auth::id()]);
        $error = match (true) {
            !password_verify($current, $hash) => 'La contraseña actual no es correcta.',
            strlen($new) < 8                  => 'La nueva contraseña debe tener al menos 8 caracteres.',
            $new !== $confirm                 => 'Las contraseñas nuevas no coinciden.',
            default                           => null,
        };
        if ($error !== null) {
            $this->flash('danger', $error);
            $this->redirect('profile');
        }
        $this->db->update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)], 'id = :id', ['id' => Auth::id()]);
        Audit::log('cambiar_clave', 'user', Auth::id());
        $this->flash('success', 'Contraseña actualizada.');
        $this->redirect('profile');
    }
}
