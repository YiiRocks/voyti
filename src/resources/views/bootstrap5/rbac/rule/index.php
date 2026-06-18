<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * @var array $rules Array of rule class names (string[])
 * @var UrlGeneratorInterface $url
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 */

/** @var UrlGeneratorInterface $url */
$url = $this->get('url');
?>
<div class="voyti-rbac-index">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0"><?= $translator->translate('voyti.view.rule.title', category: 'voyti') ?></h2>
        <a href="<?= Html::encode($url->generate('voyti/rules-create')) ?>" class="btn btn-primary"><?= $translator->translate('voyti.view.rule.create_link', category: 'voyti') ?></a>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-light">
                <tr>
                    <th scope="col"><?= $translator->translate('voyti.view.name_header', category: 'voyti') ?></th>
                    <th scope="col" class="text-end"><?= $translator->translate('voyti.view.actions_header', category: 'voyti') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rules as $ruleName): ?>
                    <tr>
                        <td><?= Html::encode($ruleName) ?></td>
                        <td class="text-end">
                            <a href="<?= Html::encode($url->generate('voyti/rules-update', ['name' => $ruleName])) ?>" class="btn btn-sm btn-outline-secondary"><?= $translator->translate('voyti.view.update_link', category: 'voyti') ?></a>
                            <form method="post" action="<?= Html::encode($url->generate('voyti/rules-delete', ['name' => $ruleName])) ?>" class="d-inline">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><?= $translator->translate('voyti.view.delete_button', category: 'voyti') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
