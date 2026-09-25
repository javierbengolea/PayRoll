<?php
use App\Core\Auth;
use App\Core\Session;

$user = Auth::user();
$current = $_GET['r'] ?? 'dashboard';
$section = explode('/', $current)[0];
$nav = [
    ['dashboard',    'speedometer2',   'Tablero',       'consulta', ['dashboard']],
    ['employees',    'people',         'Empleados',     'consulta', ['employees']],
    ['periods',      'calculator',     'Liquidaciones', 'consulta', ['periods', 'payslips']],
    ['concepts',     'list-check',     'Conceptos',     'consulta', ['concepts']],
    ['categories',   'layers',         'Categorías',    'consulta', ['categories', 'agreements']],
    ['organization', 'diagram-3',      'Organización',  'consulta', ['organization', 'departments', 'positions']],
    ['reports',      'bar-chart-line', 'Reportes',      'consulta', ['reports']],
];
$adminNav = [
    ['users',    'person-gear',  'Usuarios',      ['users']],
    ['settings', 'gear',         'Configuración', ['settings']],
    ['audit',    'journal-text', 'Auditoría',     ['audit']],
];
?>
<!doctype html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? '') !== '' ? $title . ' · ' : '') ?>PayRoll</title>
    <script>
        (function () {
            try {
                var t = localStorage.getItem('payroll-theme');
                if (!t) { t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
                document.documentElement.setAttribute('data-bs-theme', t);
            } catch (e) {}
        })();
    </script>
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('app.css') ?>">
</head>
<body>
<div class="app">
    <aside class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar">
        <div class="sidebar-brand">
            <i class="bi bi-cash-coin"></i> <span>PayRoll</span>
            <button type="button" class="btn-close btn-close-white d-lg-none ms-auto" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Cerrar"></button>
        </div>
        <nav class="sidebar-nav">
            <?php foreach ($nav as [$route, $icon, $label, $role, $match]): if (!can($role)) continue; ?>
                <a href="<?= url($route) ?>" class="<?= in_array($section, $match, true) ? 'active' : '' ?>">
                    <i class="bi bi-<?= $icon ?>"></i> <?= e($label) ?>
                </a>
            <?php endforeach; ?>
            <?php if (can('admin')): ?>
                <div class="sidebar-heading">Administración</div>
                <?php foreach ($adminNav as [$route, $icon, $label, $match]): ?>
                    <a href="<?= url($route) ?>" class="<?= in_array($section, $match, true) ? 'active' : '' ?>">
                        <i class="bi bi-<?= $icon ?>"></i> <?= e($label) ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </nav>
    </aside>

    <div class="main">
        <header class="topbar">
            <button class="btn btn-link text-body d-lg-none px-1" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-label="Menú">
                <i class="bi bi-list fs-4"></i>
            </button>
            <div class="ms-auto d-flex align-items-center gap-2">
                <button class="btn btn-sm btn-outline-secondary" type="button" id="themeToggle" title="Cambiar tema" aria-label="Cambiar tema">
                    <i class="bi bi-moon-stars"></i>
                </button>
                <div class="dropdown">
                    <button class="btn btn-sm btn-light border dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                        <span class="avatar"><?= e(mb_strtoupper(mb_substr($user['name'] ?? '?', 0, 1))) ?></span>
                        <span class="d-none d-sm-inline"><?= e($user['name'] ?? '') ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text small text-body-secondary"><?= e(ROLES[$user['role']] ?? '') ?></span></li>
                        <li><a class="dropdown-item" href="<?= url('profile') ?>"><i class="bi bi-person me-2"></i>Mi perfil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="post" action="<?= url('logout') ?>">
                                <?= csrf_field() ?>
                                <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="content">
            <?php foreach (Session::pullFlashes() as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
                    <?= e($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            <?php endforeach; ?>
            <?= $content ?>
        </main>
    </div>
</div>

<script src="<?= asset('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('app.js') ?>"></script>
<?= $scripts ?? '' ?>
</body>
</html>
