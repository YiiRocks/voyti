<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var array $assignments
 * @var array $available
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 */
?>
<div class="voyti-assignments">
    <h3 class="mb-3"><?= $translator->translate('voyti.view.assignments.title') ?></h3>
    <ul class="list-group">
        <?php foreach ($assignments as $item): ?>
            <li class="list-group-item"><?= Html::encode($item->getName()) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
