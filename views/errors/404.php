<?php $title = 'No encontrado'; ?>
<?php if (!\App\Core\Auth::check()): ?>
    <p style="font-family:sans-serif;padding:2rem">Página no encontrada. <a href="<?= url('login') ?>">Ingresar</a></p>
<?php else: ?>
<div class="empty-state">
    <i class="bi bi-signpost-split"></i>
    <h1 class="h4">No encontramos lo que buscás</h1>
    <p>La página o el registro no existe.</p>
    <a class="btn btn-primary" href="<?= url('dashboard') ?>">Volver al tablero</a>
</div>
<?php endif; ?>
