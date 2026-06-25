<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var string $username
 * @var TranslatorInterface $translator
 */
?>
<h2><?= $translator->translate('voyti.mail.welcome_heading', category: 'voyti') ?></h2>
<p><?= $translator->translate('voyti.mail.hello_username', ['username' => Html::encode($username)], category: 'voyti') ?></p>
<p><?= $translator->translate('voyti.mail.account_created_successfully', category: 'voyti') ?></p>
