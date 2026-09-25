<?php
/** @var array $map @var string $value */
[$label, $color] = $map[$value] ?? [$value, 'secondary'];
?><span class="badge text-bg-<?= $color ?>"><?= e($label) ?></span>
