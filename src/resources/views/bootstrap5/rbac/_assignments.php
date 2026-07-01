<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Rbac\Item;

/**
 * @var Item[] $assignments
 * @var Item[] $available
 * @var TranslatorInterface $translator
 */

echo Html::div()->open();
Html::H3()->class('mb-3')->open();
echo $translator->translate('voyti.view.assignments.title', category: 'voyti');
echo Html::H3()->close();

echo Html::ul()->class('list-group')->open();

foreach ($assignments as $item) {
    echo Html::li($item->getName(), ['class' => 'list-group-item']);
}

echo Html::ul()->close();
echo Html::div()->close();
