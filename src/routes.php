<?php
declare(strict_types=1);

use App\Controllers as C;

/*
 * ruta => [controlador, método, verbo HTTP, rol mínimo]
 * Rol mínimo: null = pública, 'consulta' < 'rrhh' < 'admin'.
 */
return [
    'login'                 => [C\AuthController::class, 'showLogin', 'GET', null],
    'login/submit'          => [C\AuthController::class, 'login', 'POST', null],
    'logout'                => [C\AuthController::class, 'logout', 'POST', 'consulta'],

    'dashboard'             => [C\DashboardController::class, 'index', 'GET', 'consulta'],

    'employees'             => [C\EmployeeController::class, 'index', 'GET', 'consulta'],
    'employees/show'        => [C\EmployeeController::class, 'show', 'GET', 'consulta'],
    'employees/create'      => [C\EmployeeController::class, 'create', 'GET', 'rrhh'],
    'employees/store'       => [C\EmployeeController::class, 'store', 'POST', 'rrhh'],
    'employees/edit'        => [C\EmployeeController::class, 'edit', 'GET', 'rrhh'],
    'employees/update'      => [C\EmployeeController::class, 'update', 'POST', 'rrhh'],
    'employees/concept-add' => [C\EmployeeController::class, 'addConcept', 'POST', 'rrhh'],
    'employees/concept-del' => [C\EmployeeController::class, 'removeConcept', 'POST', 'rrhh'],
    'employees/export'      => [C\EmployeeController::class, 'export', 'GET', 'consulta'],

    'organization'          => [C\OrganizationController::class, 'index', 'GET', 'consulta'],
    'departments/save'      => [C\OrganizationController::class, 'saveDepartment', 'POST', 'rrhh'],
    'departments/delete'    => [C\OrganizationController::class, 'deleteDepartment', 'POST', 'rrhh'],
    'positions/save'        => [C\OrganizationController::class, 'savePosition', 'POST', 'rrhh'],
    'positions/delete'      => [C\OrganizationController::class, 'deletePosition', 'POST', 'rrhh'],

    'categories'            => [C\CategoryController::class, 'index', 'GET', 'consulta'],
    'agreements/save'       => [C\CategoryController::class, 'saveAgreement', 'POST', 'rrhh'],
    'categories/save'       => [C\CategoryController::class, 'saveCategory', 'POST', 'rrhh'],
    'categories/delete'     => [C\CategoryController::class, 'deleteCategory', 'POST', 'rrhh'],
    'categories/scale'      => [C\CategoryController::class, 'saveScale', 'POST', 'rrhh'],
    'categories/scale-del'  => [C\CategoryController::class, 'deleteScale', 'POST', 'rrhh'],

    'concepts'              => [C\ConceptController::class, 'index', 'GET', 'consulta'],
    'concepts/create'       => [C\ConceptController::class, 'create', 'GET', 'rrhh'],
    'concepts/store'        => [C\ConceptController::class, 'store', 'POST', 'rrhh'],
    'concepts/edit'         => [C\ConceptController::class, 'edit', 'GET', 'rrhh'],
    'concepts/update'       => [C\ConceptController::class, 'update', 'POST', 'rrhh'],
    'concepts/toggle'       => [C\ConceptController::class, 'toggle', 'POST', 'rrhh'],
    'concepts/preview'      => [C\ConceptController::class, 'preview', 'POST', 'rrhh'],

    'periods'               => [C\PeriodController::class, 'index', 'GET', 'consulta'],
    'periods/store'         => [C\PeriodController::class, 'store', 'POST', 'rrhh'],
    'periods/show'          => [C\PeriodController::class, 'show', 'GET', 'consulta'],
    'periods/calculate'     => [C\PeriodController::class, 'calculate', 'POST', 'rrhh'],
    'periods/close'         => [C\PeriodController::class, 'close', 'POST', 'rrhh'],
    'periods/reopen'        => [C\PeriodController::class, 'reopen', 'POST', 'admin'],
    'periods/delete'        => [C\PeriodController::class, 'delete', 'POST', 'rrhh'],
    'periods/novelty-add'   => [C\PeriodController::class, 'addNovelty', 'POST', 'rrhh'],
    'periods/novelty-del'   => [C\PeriodController::class, 'deleteNovelty', 'POST', 'rrhh'],
    'periods/export'        => [C\PeriodController::class, 'export', 'GET', 'consulta'],
    'periods/bank'          => [C\PeriodController::class, 'bankFile', 'GET', 'rrhh'],

    'payslips/show'         => [C\PayslipController::class, 'show', 'GET', 'consulta'],
    'payslips/print'        => [C\PayslipController::class, 'printPeriod', 'GET', 'consulta'],

    'reports'               => [C\ReportController::class, 'index', 'GET', 'consulta'],

    'profile'               => [C\ProfileController::class, 'index', 'GET', 'consulta'],
    'profile/password'      => [C\ProfileController::class, 'password', 'POST', 'consulta'],

    'users'                 => [C\UserController::class, 'index', 'GET', 'admin'],
    'users/save'            => [C\UserController::class, 'save', 'POST', 'admin'],
    'settings'              => [C\SettingsController::class, 'index', 'GET', 'admin'],
    'settings/save'         => [C\SettingsController::class, 'save', 'POST', 'admin'],
    'audit'                 => [C\AuditController::class, 'index', 'GET', 'admin'],
];
