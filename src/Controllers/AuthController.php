<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\View;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            $this->redirect('dashboard');
        }
        View::render('auth/login', [], null);
    }

    public function login(): void
    {
        $email = (string) $this->input('email', '');
        $error = Auth::attempt($email, (string) ($_POST['password'] ?? ''));
        if ($error !== null) {
            $this->flash('danger', $error);
            $this->redirect('login', ['email' => $email]);
        }
        $this->redirect('dashboard');
    }

    public function logout(): void
    {
        Auth::logout();
        $this->flash('success', 'Cerraste la sesión.');
        $this->redirect('login');
    }
}
