<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var string $username
 * @var TranslatorInterface $translator
 */

echo Html::H2($translator->translate('voyti.mail.welcome_heading'));

echo Html::p($translator->translate('voyti.mail.hello_username', ['username' => $username]));

echo Html::p($translator->translate('voyti.mail.account_created_successfully'));
