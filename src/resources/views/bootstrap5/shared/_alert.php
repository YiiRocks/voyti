<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var string $type
 * @var string $message
 */
?>
<div class="alert alert-<?= Html::encode($type) ?> alert-dismissible fade show shadow-sm" role="alert">
    <?= Html::encode($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
