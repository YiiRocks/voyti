<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var string $code
 * @var TranslatorInterface $translator
 */

echo Html::H2($translator->translate('voyti.mail.twofactor_heading', category: 'voyti'));

echo Html::p($translator->translate('voyti.mail.your_twofactor_code', category: 'voyti'));

echo Html::p($code)->addStyle('font-size: 24px; font-weight: bold; letter-spacing: 4px;');
