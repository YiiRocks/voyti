<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var string $username
 * @var string $recoveryUrl
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 */
?>
<h2><?= $translator->translate('voyti.mail.password_recovery_heading') ?></h2>
<p><?= $translator->translate('voyti.mail.hello_username', ['username' => Html::encode($username)]) ?></p>
<p><?= $translator->translate('voyti.mail.click_to_reset_password') ?></p>
<p><a href="<?= Html::encode($recoveryUrl) ?>"><?= Html::encode($recoveryUrl) ?></a></p>
