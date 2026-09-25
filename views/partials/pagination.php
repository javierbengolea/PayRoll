<?php
/** @var int $page @var int $pages @var string $route */
if ($pages <= 1) return;
$query = $_GET;
unset($query['r']);
$link = function (int $p) use ($route, $query) { return url($route, ['page' => $p] + $query); };
$start = max(1, $page - 2);
$end = min($pages, $page + 2);
?>
<nav aria-label="Paginación">
    <ul class="pagination pagination-sm justify-content-end mb-0">
        <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= e($link($page - 1)) ?>">&laquo;</a></li>
        <?php for ($p = $start; $p <= $end; $p++): ?>
            <li class="page-item<?= $p === $page ? ' active' : '' ?>"><a class="page-link" href="<?= e($link($p)) ?>"><?= $p ?></a></li>
        <?php endfor; ?>
        <li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>"><a class="page-link" href="<?= e($link($page + 1)) ?>">&raquo;</a></li>
    </ul>
</nav>
