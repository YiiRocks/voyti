<?php

declare(strict_types=1);

/**
 * @var string $code
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 */
?>
<h2><?= $translator->translate('voyti.mail.twofactor_heading', category: 'voyti') ?></h2>
<p><?= $translator->translate('voyti.mail.your_twofactor_code', category: 'voyti') ?></p>
<p style="font-size: 24px; font-weight: bold; letter-spacing: 4px;"><?= $code ?></p>
