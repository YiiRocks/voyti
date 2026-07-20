<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var string $username
 * @var string $confirmationUrl
 * @var TranslatorInterface $translator
 */

echo Html::h2($translator->translate('voyti.mail.confirm_account_heading'));

echo Html::p($translator->translate('voyti.mail.hello_username', ['username' => $username]));

echo Html::p($translator->translate('voyti.mail.click_to_confirm_account'));

echo Html::p(
    Html::a($confirmationUrl, $confirmationUrl),
);
