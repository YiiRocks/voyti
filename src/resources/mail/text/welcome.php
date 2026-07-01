<?php

declare(strict_types=1);

use Yiisoft\Translator\TranslatorInterface;

/**
 * @var TranslatorInterface $translator
 * @var string $username
 */

?><?= $translator->translate('voyti.mail.welcome_heading', category: 'voyti') ?>

<?= $translator->translate('voyti.mail.hello_username', ['username' => $username], category: 'voyti') ?>

<?= $translator->translate('voyti.mail.account_created_successfully', category: 'voyti') ?>
