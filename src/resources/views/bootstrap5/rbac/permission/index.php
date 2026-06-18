<?php

declare(strict_types=1);
use Yiisoft\FormModel\Field;

use Yiisoft\Html\Html;
use Yiisoft\Html\Tag\Button;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var array $items Array of Permission objects
 * @var string $filterName
 * @var string $filterDescription
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 */

/** @var UrlGeneratorInterface $url */
$url = $this->get('url');
?>
<?php $this->setTitle($translator->translate('voyti.view.permission.title', category: 'voyti')); ?>
<div class="voyti-rbac-index">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1><?= $translator->translate('voyti.view.permission.title', category: 'voyti') ?></h1>
        <a href="<?= Html::encode($url->generate('voyti/permissions-create')) ?>" class="btn btn-primary"><?= $translator->translate('voyti.view.permission.create_link', category: 'voyti') ?></a>
    </div>
<?php
$form = Html::form(
    $url->generate('voyti/permissions-index'),
    'get',
    ['class' => 'mb-3']
);
?>
<?= $form->begin() ?>
    <div class="row g-2">
        <div class="col">
            <input type="text" class="form-control" name="name" value="<?= Html::encode($filterName) ?>" placeholder="<?= $translator->translate('voyti.view.name_label', category: 'voyti') ?>">
        </div>
        <div class="col">
            <input type="text" class="form-control" name="description" value="<?= Html::encode($filterDescription) ?>" placeholder="<?= $translator->translate('voyti.view.description_label', category: 'voyti') ?>">
        </div>
        <div class="col-auto">
            <?= Field::buttonGroup()
                ->buttons(
                    Button::submit($translator->translate('voyti.view.filter_button', category: 'voyti'))->class('btn', 'btn-outline-secondary')
                )
?>
        </div>
    </div>
    <?= $form->end() ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-light">
                <tr>
                    <th scope="col"><?= $translator->translate('voyti.view.name_header', category: 'voyti') ?></th>
                    <th scope="col"><?= $translator->translate('voyti.view.description_header', category: 'voyti') ?></th>
                    <th scope="col" class="text-end"><?= $translator->translate('voyti.view.actions_header', category: 'voyti') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $perm): ?>
                    <tr>
                        <td><?= Html::encode($perm->getName()) ?></td>
                        <td><?= Html::encode($perm->getDescription()) ?></td>
                        <td class="text-end">
                            <a href="<?= Html::encode($url->generate('voyti/permissions-update', ['name' => $perm->getName()])) ?>" class="btn btn-sm btn-outline-secondary"><?= $translator->translate('voyti.view.update_link', category: 'voyti') ?></a>
                            <?php
                $deleteForm = Html::form(
                    $url->generate('voyti/permissions-delete', ['name' => $perm->getName()]),
                    'post',
                    ['class' => 'd-inline']
                );
                    ?>
                            <?= $deleteForm->begin() ?>
                                <?= Field::buttonGroup()
                            ->buttons(
                                Button::submit($translator->translate('voyti.view.delete_button', category: 'voyti'))->class('btn', 'btn-sm', 'btn-outline-danger')
                            )
                    ?>
                                <?= $deleteForm->end() ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-light">
                <tr>
                    <th scope="col"><?= $translator->translate('voyti.view.name_header', category: 'voyti') ?></th>
                    <th scope="col"><?= $translator->translate('voyti.view.description_header', category: 'voyti') ?></th>
                    <th scope="col" class="text-end"><?= $translator->translate('voyti.view.actions_header', category: 'voyti') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $perm): ?>
                    <tr>
                        <td><?= Html::encode($perm->getName()) ?></td>
                        <td><?= Html::encode($perm->getDescription()) ?></td>
                        <td class="text-end">
                            <a href="<?= Html::encode($url->generate('voyti/permissions-update', ['name' => $perm->getName()])) ?>" class="btn btn-sm btn-outline-secondary"><?= $translator->translate('voyti.view.update_link', category: 'voyti') ?></a>
                            <form method="post" action="<?= Html::encode($url->generate('voyti/permissions-delete', ['name' => $perm->getName()])) ?>" class="d-inline">
                                <?= Field::buttonGroup()
                    ->buttons(
                        Button::submit($translator->translate('voyti.view.delete_button', category: 'voyti'))->class('btn', 'btn-sm', 'btn-outline-danger')
                    )
                    ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
