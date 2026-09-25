<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Validator;
use App\Repositories\Settings;

final class SettingsController extends Controller
{
    private const KEYS = ['company_name', 'company_cuit', 'company_address', 'company_activity', 'payment_place', 'hours_divisor'];

    public function index(): void
    {
        $this->render('settings/index', ['settings' => Settings::all()]);
    }

    public function save(): void
    {
        $v = Validator::make($_POST, [
            'company_name'  => 'required|maxlen:160',
            'company_cuit'  => 'required|cuil',
            'hours_divisor' => 'required|integer|min:1|max:400',
        ], ['company_name' => 'Razón social', 'company_cuit' => 'CUIT', 'hours_divisor' => 'Divisor de horas']);
        if ($v->fails()) {
            $this->failValidation($v->errors(), 'settings');
        }
        foreach (self::KEYS as $key) {
            $value = trim((string) ($_POST[$key] ?? ''));
            if ($key === 'company_cuit') {
                $value = preg_replace('/\D/', '', $value);
            }
            Settings::set($key, $value);
        }
        Audit::log('editar', 'settings');
        $this->flash('success', 'Configuración guardada.');
        $this->redirect('settings');
    }
}
