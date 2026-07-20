<?php

declare(strict_types=1);

use Yiisoft\Translator\TranslatorInterface;

/**
 * @var string $code
 * @var TranslatorInterface $translator
 */

?><?= $translator->translate('voyti.mail.twofactor_heading') ?>

<?= $translator->translate('voyti.mail.your_twofactor_code') ?>

<?= $code ?>
