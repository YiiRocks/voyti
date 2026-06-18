<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * @var array $users
 * @var array $filters
 * @var int $totalPages
 * @var int $currentPage
 * @var UrlGeneratorInterface $url
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 */

/** @var UrlGeneratorInterface $url */
$url = $this->get('url');
?>
<div class="voyti-admin-index">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0"><?= $translator->translate('voyti.view.admin.title', category: 'voyti') ?></h2>
        <a href="<?= Html::encode($url->generate('voyti/admin-create')) ?>" class="btn btn-primary"><?= $translator->translate('voyti.view.admin.create_user_link', category: 'voyti') ?></a>
    </div>

    <form method="get" class="mb-3">
        <div class="row g-2">
            <div class="col">
                <input type="text" class="form-control" name="username" value="<?= Html::encode($filters['username'] ?? '') ?>" placeholder="<?= $translator->translate('voyti.view.username_header', category: 'voyti') ?>">
            </div>
            <div class="col">
                <input type="text" class="form-control" name="email" value="<?= Html::encode($filters['email'] ?? '') ?>" placeholder="<?= $translator->translate('voyti.view.email_header', category: 'voyti') ?>">
            </div>
            <div class="col">
                <select class="form-select" name="status">
                    <option value=""><?= $translator->translate('voyti.view.status_header', category: 'voyti') ?></option>
                    <option value="confirmed" <?= ($filters['status'] ?? '') === 'confirmed' ? 'selected' : '' ?>><?= $translator->translate('voyti.view.status_active', category: 'voyti') ?></option>
                    <option value="unconfirmed" <?= ($filters['status'] ?? '') === 'unconfirmed' ? 'selected' : '' ?>><?= $translator->translate('voyti.view.status_pending', category: 'voyti') ?></option>
                    <option value="blocked" <?= ($filters['status'] ?? '') === 'blocked' ? 'selected' : '' ?>><?= $translator->translate('voyti.view.status_blocked', category: 'voyti') ?></option>
                </select>
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
                    <th scope="col"><?= $translator->translate('voyti.view.id_header', category: 'voyti') ?></th>
                    <th scope="col"><?= $translator->translate('voyti.view.username_header', category: 'voyti') ?></th>
                    <th scope="col"><?= $translator->translate('voyti.view.email_header', category: 'voyti') ?></th>
                    <th scope="col"><?= $translator->translate('voyti.view.status_header', category: 'voyti') ?></th>
                    <th scope="col" class="text-end"><?= $translator->translate('voyti.view.actions_header', category: 'voyti') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= Html::encode((string)$user->getId()) ?></td>
                        <td><?= Html::encode($user->getUsername()) ?></td>
                        <td><?= Html::encode($user->getEmail()) ?></td>
                        <td>
                            <?php if ($user->isBlocked()): ?>
                                <span class="badge bg-danger"><?= $translator->translate('voyti.view.status_blocked', category: 'voyti') ?></span>
                            <?php elseif ($user->isConfirmed()): ?>
                                <span class="badge bg-success"><?= $translator->translate('voyti.view.status_active', category: 'voyti') ?></span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark"><?= $translator->translate('voyti.view.status_pending', category: 'voyti') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="<?= Html::encode($url->generate('voyti/admin-update', ['id' => $user->getId()])) ?>" class="btn btn-sm btn-outline-secondary"><?= $translator->translate('voyti.view.update_link', category: 'voyti') ?></a>
                            <a href="<?= Html::encode($url->generate('voyti/admin-info', ['id' => $user->getId()])) ?>" class="btn btn-sm btn-outline-info"><?= $translator->translate('voyti.view.admin.info_link', category: 'voyti') ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <?php if ($currentPage > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="<?= Html::encode($url->generate('voyti/admin', [], ['page' => $currentPage - 1, 'username' => $filters['username'] ?? '', 'email' => $filters['email'] ?? '', 'status' => $filters['status'] ?? ''])) ?>"><?= $translator->translate('voyti.view.previous', category: 'voyti') ?></a>
                </li>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                    <a class="page-link" href="<?= Html::encode($url->generate('voyti/admin', [], ['page' => $i, 'username' => $filters['username'] ?? '', 'email' => $filters['email'] ?? '', 'status' => $filters['status'] ?? ''])) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <?php if ($currentPage < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="<?= Html::encode($url->generate('voyti/admin', [], ['page' => $currentPage + 1, 'username' => $filters['username'] ?? '', 'email' => $filters['email'] ?? '', 'status' => $filters['status'] ?? ''])) ?>"><?= $translator->translate('voyti.view.next', category: 'voyti') ?></a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>
