<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var array $users
 * @var array $filters
 * @var int $totalPages
 * @var int $currentPage
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

$this->setTitle($translator->translate('voyti.view.admin.title', category: 'voyti'));

echo Html::div()->class('voyti-admin-index')->open();
    echo Html::div()->class('d-flex justify-content-between align-items-center mb-3')->open();
        echo Html::H1($translator->translate('voyti.view.admin.title', category: 'voyti'));
        echo Html::a($translator->translate('voyti.view.admin.create_user_link', category: 'voyti'), $url->generate('voyti/admin-create'))->class('btn', 'btn-primary');
    echo Html::div()->close();

    echo Html::form()
        ->action($url->generate('voyti/admin-index'))
        ->method('get')
        ->open();

    echo Html::div()->class('row g-2')->open();
        echo Html::div()->class('col')->open();
            echo Html::input('text')->class('form-control')->name('username')->value(Html::encode($filters['username'] ?? ''))->placeholder($translator->translate('voyti.view.username_header', category: 'voyti'));
        echo Html::div()->close();

        echo Html::div()->class('col')->open();
            echo Html::input('text')->class('form-control')->name('email')->value(Html::encode($filters['email'] ?? ''))->placeholder($translator->translate('voyti.view.email_header', category: 'voyti'));
        echo Html::div()->close();

        echo Html::div()->class('col')->open();
            echo '<select class="form-select" name="status">' . "\n";
            echo '    <option value="">' . $translator->translate('voyti.view.status_header', category: 'voyti') . '</option>' . "\n";
            echo '    <option value="confirmed"' . (($filters['status'] ?? '') === 'confirmed' ? ' selected' : '') . '>' . $translator->translate('voyti.view.status_active', category: 'voyti') . '</option>' . "\n";
            echo '    <option value="unconfirmed"' . (($filters['status'] ?? '') === 'unconfirmed' ? ' selected' : '') . '>' . $translator->translate('voyti.view.status_pending', category: 'voyti') . '</option>' . "\n";
            echo '    <option value="blocked"' . (($filters['status'] ?? '') === 'blocked' ? ' selected' : '') . '>' . $translator->translate('voyti.view.status_blocked', category: 'voyti') . '</option>' . "\n";
            echo '</select>' . "\n";
        echo Html::div()->close();

        echo Html::div()->class('col-auto')->open();
            echo Field::buttonGroup()
                ->buttons(
                    Html::submitButton($translator->translate('voyti.view.filter_button', category: 'voyti'))->class('btn', 'btn-outline-secondary')
                );
        echo Html::div()->close();
    echo Html::div()->close();

    echo Html::form()->close();

    echo Html::div()->class('table-responsive')->open();
        echo Html::table()->class('table table-striped table-hover')->open();

        echo Html::tag('thead')->class('table-light')->open();
            echo Html::tag('tr')->open();
                echo Html::tag('th', $translator->translate('voyti.view.id_header', category: 'voyti'))->scope('col');
                echo Html::tag('th', $translator->translate('voyti.view.username_header', category: 'voyti'))->scope('col');
                echo Html::tag('th', $translator->translate('voyti.view.email_header', category: 'voyti'))->scope('col');
                echo Html::tag('th', $translator->translate('voyti.view.status_header', category: 'voyti'))->scope('col');
                echo Html::tag('th', $translator->translate('voyti.view.actions_header', category: 'voyti'))->class('text-end')->scope('col');
            echo Html::tag('tr')->close();
        echo Html::tag('thead')->close();

        echo Html::tag('tbody')->open();

        foreach ($users as $user) {
            echo Html::tag('tr')->open();
                echo Html::tag('td', Html::encode($user->getId()));
                echo Html::tag('td', Html::encode($user->getUsername()));
                echo Html::tag('td', Html::encode($user->getEmail()));
                echo Html::tag('td')->open();

                if ($user->isBlocked()) {
                    echo Html::tag('span', $translator->translate('voyti.view.status_blocked', category: 'voyti'))->class('badge bg-danger');
                } elseif ($user->isConfirmed()) {
                    echo Html::tag('span', $translator->translate('voyti.view.status_active', category: 'voyti'))->class('badge bg-success');
                } else {
                    echo Html::tag('span', $translator->translate('voyti.view.status_pending', category: 'voyti'))->class('badge bg-warning text-dark');
                }

                echo Html::tag('td')->close();

                echo Html::tag('td')->class('text-end')->open();
                    echo Html::a($translator->translate('voyti.view.info_link', category: 'voyti'), $url->generate('voyti/admin-info', ['id' => $user->getId()]))->class('btn', 'btn-sm', 'btn-outline-secondary');
                    echo ' ';
                    echo Html::a($translator->translate('voyti.view.update_link', category: 'voyti'), $url->generate('voyti/admin-update', ['id' => $user->getId()]))->class('btn', 'btn-sm', 'btn-outline-secondary');
                    echo ' ';

                    echo Html::form()
                        ->post($url->generate('voyti/admin-delete', ['id' => $user->getId()]))
                        ->csrf($csrf)
                        ->class('d-inline')
                        ->open();

                    echo Field::buttonGroup()
                        ->buttons(
                            Html::submitButton($translator->translate('voyti.view.delete_button', category: 'voyti'))->class('btn', 'btn-sm', 'btn-outline-danger')
                        );

                    echo Html::form()->close();
                echo Html::tag('td')->close();
            echo Html::tag('tr')->close();
        }

        echo Html::tag('tbody')->close();
        echo Html::table()->close();
    echo Html::div()->close();

    if ($totalPages > 1) {
        echo '<nav aria-label="Page navigation">' . "\n";
        echo '    <ul class="pagination justify-content-center">' . "\n";

        if ($currentPage > 1) {
            echo '        <li class="page-item">' . "\n";
            echo '            <a class="page-link" href="' . Html::encode($url->generate('voyti/admin', [], ['page' => $currentPage - 1, 'username' => $filters['username'] ?? '', 'email' => $filters['email'] ?? '', 'status' => $filters['status'] ?? ''])) . '">' . $translator->translate('voyti.view.previous', category: 'voyti') . '</a>' . "\n";
            echo '        </li>' . "\n";
        }

        for ($i = 1; $i <= $totalPages; $i++) {
            echo '        <li class="page-item' . ($i === $currentPage ? ' active' : '') . '">' . "\n";
            echo '            <a class="page-link" href="' . Html::encode($url->generate('voyti/admin', [], ['page' => $i, 'username' => $filters['username'] ?? '', 'email' => $filters['email'] ?? '', 'status' => $filters['status'] ?? ''])) . '">' . $i . '</a>' . "\n";
            echo '        </li>' . "\n";
        }

        if ($currentPage < $totalPages) {
            echo '        <li class="page-item">' . "\n";
            echo '            <a class="page-link" href="' . Html::encode($url->generate('voyti/admin', [], ['page' => $currentPage + 1, 'username' => $filters['username'] ?? '', 'email' => $filters['email'] ?? '', 'status' => $filters['status'] ?? ''])) . '">' . $translator->translate('voyti.view.next', category: 'voyti') . '</a>' . "\n";
            echo '        </li>' . "\n";
        }

        echo '    </ul>' . "\n";
        echo '</nav>' . "\n";
    }
echo Html::div()->close();
