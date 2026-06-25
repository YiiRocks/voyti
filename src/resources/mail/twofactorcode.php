<?php

declare(strict_types=1);

use Yiisoft\Translator\TranslatorInterface;

/**
 * @var string $code
 * @var TranslatorInterface $translator
 */
?>
<h2><?= $translator->translate('voyti.mail.twofactor_heading', category: 'voyti') ?></h2>
<p><?= $translator->translate('voyti.mail.your_twofactor_code', category: 'voyti') ?></p>
<p style="font-size: 24px; font-weight: bold; letter-spacing: 4px;"><?= $code ?></p>
