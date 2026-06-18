<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var string $username
 * @var string $confirmationUrl
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 */
?>
<h2><?= $translator->translate('voyti.mail.confirm_account_heading') ?></h2>
<p><?= $translator->translate('voyti.mail.hello_username', ['username' => Html::encode($username)]) ?></p>
<p><?= $translator->translate('voyti.mail.click_to_confirm_account') ?></p>
<p><a href="<?= Html::encode($confirmationUrl) ?>"><?= Html::encode($confirmationUrl) ?></a></p>
