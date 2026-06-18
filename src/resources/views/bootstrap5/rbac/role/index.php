<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * @var array $items Array of Role objects
 * @var string $filterName
 * @var string $filterDescription
 * @var UrlGeneratorInterface $url
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 */

/** @var UrlGeneratorInterface $url */
$url = $this->get('url');
?>
<div class="voyti-rbac-index">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0"><?= $translator->translate('voyti.view.role.title', category: 'voyti') ?></h2>
        <a href="<?= Html::encode($url->generate('voyti/roles-create')) ?>" class="btn btn-primary"><?= $translator->translate('voyti.view.role.create_link', category: 'voyti') ?></a>
    </div>
    <form method="get" class="mb-3">
        <div class="row g-2">
            <div class="col">
                <input type="text" class="form-control" name="name" value="<?= Html::encode($filterName) ?>" placeholder="<?= $translator->translate('voyti.view.name_label', category: 'voyti') ?>">
            </div>
            <div class="col">
                <input type="text" class="form-control" name="description" value="<?= Html::encode($filterDescription) ?>" placeholder="<?= $translator->translate('voyti.view.description_label', category: 'voyti') ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-outline-secondary"><?= $translator->translate('voyti.view.filter_button', category: 'voyti') ?></button>
            </div>
        </div>
    </form>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-light">
                <tr>
                    <th scope="col"><?= $translator->translate('voyti.view.name_header', category: 'voyti') ?></th>
                    <th scope="col"><?= $translator->translate('voyti.view.description_header', category: 'voyti') ?></th>
                    <th scope="col"><?= $translator->translate('voyti.view.children_header', category: 'voyti') ?></th>
                    <th scope="col" class="text-end"><?= $translator->translate('voyti.view.actions_header', category: 'voyti') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $role): ?>
                    <tr>
                        <td><?= Html::encode($role->getName()) ?></td>
                        <td><?= Html::encode($role->getDescription()) ?></td>
                        <td><?= Html::encode(implode(', ', array_map(fn($c) => $c->getName(), $role instanceof \Yiisoft\Rbac\Role ? [] : []))) ?></td>
                        <td class="text-end">
                            <a href="<?= Html::encode($url->generate('voyti/roles-update', ['name' => $role->getName()])) ?>" class="btn btn-sm btn-outline-secondary"><?= $translator->translate('voyti.view.update_link', category: 'voyti') ?></a>
                            <form method="post" action="<?= Html::encode($url->generate('voyti/roles-delete', ['name' => $role->getName()])) ?>" class="d-inline">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><?= $translator->translate('voyti.view.delete_button', category: 'voyti') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
