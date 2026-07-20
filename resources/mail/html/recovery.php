<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var string $username
 * @var string $recoveryUrl
 * @var TranslatorInterface $translator
 */

echo Html::H2($translator->translate('voyti.mail.password_recovery_heading'));

echo Html::p($translator->translate('voyti.mail.hello_username', ['username' => $username]));

echo Html::p($translator->translate('voyti.mail.click_to_reset_password'));

echo Html::p(
    Html::a($recoveryUrl, $recoveryUrl),
);
