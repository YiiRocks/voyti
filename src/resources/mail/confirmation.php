<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var string $username
 * @var string $confirmationUrl
 * @var TranslatorInterface $translator
 */
?>
<h2><?= $translator->translate('voyti.mail.confirm_account_heading', category: 'voyti') ?></h2>
<p><?= $translator->translate('voyti.mail.hello_username', ['username' => Html::encode($username)], category: 'voyti') ?></p>
<p><?= $translator->translate('voyti.mail.click_to_confirm_account', category: 'voyti') ?></p>
<p><a href="<?= Html::encode($confirmationUrl) ?>"><?= Html::encode($confirmationUrl) ?></a></p>
