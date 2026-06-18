<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var YiiRocks\Voyti\Form\Auth\RegistrationForm $model
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var array $errors
 */
?>
<?php $this->setTitle($translator->translate('voyti.view.admin.create_user_title', category: 'voyti')); ?>
<div class="voyti-admin-create">
    <h1><?= $translator->translate('voyti.view.admin.create_user_title', category: 'voyti') ?></h1>
    <form action="<?= Html::encode($url->generate('voyti/admin-create')) ?>" method="post" novalidate>
        <?= Field::errorSummary(null)->errors($errors) ?>
        <?= Field::text($model, 'username')->name('user[username]')->value($model->username) ?>
        <?= Field::email($model, 'email')->name('user[email]')->value($model->email) ?>
        <?= Field::password($model, 'password')->name('user[password]') ?>
        <?= Field::buttonGroup()
            ->buttons(
                Html::submitButton($translator->translate('voyti.view.create_button', category: 'voyti'))
            )
?>
    </form>
</div>
