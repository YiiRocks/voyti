<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var string $username
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 */
?>
<h2><?= $translator->translate('voyti.mail.welcome_heading') ?></h2>
<p><?= $translator->translate('voyti.mail.hello_username', ['username' => Html::encode($username)]) ?></p>
<p><?= $translator->translate('voyti.mail.account_created_successfully') ?></p>
