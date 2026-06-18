<?php

declare(strict_types=1);
use YiiRocks\Voyti\Entity\User;

use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Html\Tag\Button;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var User $user
 * @var ModuleConfig $config
 * @var array $assignments Array of assigned item names (string[])
 * @var array $available Array of unassigned items (name => Item)
 * @var TranslatorInterface $translator
 * @var UrlGeneratorInterface $url
 */

/** @var UrlGeneratorInterface $url */
$url = $this->get('url');
?>
<?php $this->setTitle($translator->translate('voyti.view.assignments.title', category: 'voyti')); ?>
<div class="voyti-assignments">
    <h3 class="mb-3"><?= $translator->translate('voyti.view.assignments.title', category: 'voyti') ?></h3>
    <form method="post" action="<?= $url->generate('voyti/admin-assignments', ['id' => $user->getId()]) ?>">
        <table class="table">
            <thead>
                <tr>
                    <th><?= $translator->translate('voyti.view.assignments.assigned', category: 'voyti') ?></th>
                    <th><?= $translator->translate('voyti.view.assignments.available', category: 'voyti') ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <?php foreach ($assignments as $itemName): ?>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="items[]" value="<?= Html::encode($itemName) ?>" checked>
                                <label class="form-check-label"><?= Html::encode($itemName) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <?php foreach ($available as $name => $item): ?>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="items[]" value="<?= Html::encode($name) ?>">
                                <label class="form-check-label"><?= Html::encode($name) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <?= Field::buttonGroup()
            ->buttons(
                Button::submit($translator->translate('voyti.view.assignments.update', category: 'voyti'))->class('btn', 'btn-primary')
            )
?>
    </form>
</div>
