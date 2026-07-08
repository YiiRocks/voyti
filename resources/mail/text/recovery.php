<?php

declare(strict_types=1);

use Yiisoft\Translator\TranslatorInterface;

/**
 * @var string $recoveryUrl
 * @var TranslatorInterface $translator
 * @var string $username
 */

?><?= $translator->translate('voyti.mail.password_recovery_heading', category: 'voyti') ?>

<?= $translator->translate('voyti.mail.hello_username', ['username' => $username], category: 'voyti') ?>

<?= $translator->translate('voyti.mail.click_to_reset_password', category: 'voyti') ?>

<?= $recoveryUrl ?>
