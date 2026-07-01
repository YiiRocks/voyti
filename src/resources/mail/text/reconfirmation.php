<?php

declare(strict_types=1);

use Yiisoft\Translator\TranslatorInterface;

/**
 * @var string $confirmationUrl
 * @var TranslatorInterface $translator
 * @var string $username
 */

?><?= $translator->translate('voyti.mail.email_change_heading', category: 'voyti') ?>

<?= $translator->translate('voyti.mail.hello_username', ['username' => $username], category: 'voyti') ?>

<?= $translator->translate('voyti.mail.click_to_confirm_email', category: 'voyti') ?>

<?= $confirmationUrl ?>
