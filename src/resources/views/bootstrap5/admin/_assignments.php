<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * @var \YiiRocks\Voyti\Entity\User $user
 * @var \YiiRocks\Voyti\ModuleConfig $config
 * @var array $assignments Array of assigned item names (string[])
 * @var array $available Array of unassigned items (name => Item)
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 * @var UrlGeneratorInterface $url
 */

/** @var UrlGeneratorInterface $url */
$url = $this->get('url');
?>
<div class="voyti-assignments">
    <h3 class="mb-3"><?= $translator->translate('voyti.view.assignments.title') ?></h3>
    <form method="post" action="<?= $url->generate('voyti/admin-assignments', ['id' => $user->getId()]) ?>">
        <table class="table">
            <thead>
                <tr>
                    <th><?= $translator->translate('voyti.view.assignments.assigned') ?></th>
                    <th><?= $translator->translate('voyti.view.assignments.available') ?></th>
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
        <button type="submit" class="btn btn-primary"><?= $translator->translate('voyti.view.assignments.update') ?></button>
    </form>
</div>
