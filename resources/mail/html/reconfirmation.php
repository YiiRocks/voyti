<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var string $username
 * @var string $confirmationUrl
 * @var TranslatorInterface $translator
 */

echo Html::H2($translator->translate('voyti.mail.email_change_heading', category: 'voyti'));

echo Html::p($translator->translate('voyti.mail.hello_username', ['username' => $username], category: 'voyti'));

echo Html::p($translator->translate('voyti.mail.click_to_confirm_email', category: 'voyti'));

echo Html::p(
    Html::a($confirmationUrl, $confirmationUrl),
);
