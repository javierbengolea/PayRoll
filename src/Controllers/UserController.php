<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Validator;

final class UserController extends Controller
{
    public function index(): void
    {
        $users = $this->db->all('SELECT id, name, email, role, active, last_login_at, locked_until, created_at FROM users ORDER BY name');
        $this->render('users/index', compact('users'));
    }

    public function save(): void
    {
        $id = $this->intParam('id');
        $email = mb_strtolower((string) $this->input('email', ''));
        $password = (string) ($_POST['password'] ?? '');

        $v = Validator::make($_POST, [
            'name'  => 'required|maxlen:120',
            'email' => 'required|email|maxlen:190',
            'role'  => 'required|in:' . implode(',', array_keys(ROLES)),
        ], ['name' => 'Nombre', 'email' => 'Email', 'role' => 'Rol']);
        if ($this->db->value('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $id])) {
            $v->addError('email', 'Ya existe un usuario con ese email.');
        }
        if (!$id && $password === '') {
            $v->addError('password', 'La contraseña es obligatoria para un usuario nuevo.');
        }
        if ($password !== '' && strlen($password) < 8) {
            $v->addError('password', 'La contraseña debe tener al menos 8 caracteres.');
        }
        $active = isset($_POST['active']) ? 1 : 0;
        if ($id === Auth::id() && (!$active || $_POST['role'] !== 'admin')) {
            $v->addError('role', 'No podés quitarte el rol de administrador ni desactivar tu propio usuario.');
        }
        if ($v->fails()) {
            $this->flash('danger', implode(' ', $v->errors()));
            $this->redirect('users');
        }

        $data = ['name' => (string) $this->input('name'), 'email' => $email, 'role' => $_POST['role'], 'active' => $active];
        if ($password !== '') {
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $data['failed_logins'] = 0;
            $data['locked_until'] = null;
        }
        if ($id) {
            $this->db->update('users', $data, 'id = :id', ['id' => $id]);
        } else {
            $id = $this->db->insert('users', $data);
        }
        Audit::log('guardar', 'user', $id, $email . ' (' . $data['role'] . ')');
        $this->flash('success', 'Usuario guardado.');
        $this->redirect('users');
    }
}
