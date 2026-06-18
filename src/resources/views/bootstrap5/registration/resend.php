<?php

declare(strict_types=1);

use YiiRocks\Voyti\Helper\RecaptchaHelper;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var YiiRocks\Voyti\Form\Auth\ResendForm $model
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 */
?>
<?php $this->setTitle($translator->translate('voyti.view.registration.resend_title', category: 'voyti')); ?>
<div class="voyti-resend">
    <h1><?= $translator->translate('voyti.view.registration.resend_title', category: 'voyti') ?></h1>
    <form action="<?= Html::encode($url->generate('voyti/resend')) ?>" method="post" novalidate>
        <?= Field::email($model, 'email') ?>
        <?= RecaptchaHelper::render($model, $config) ?>
        <?= Field::buttonGroup()
            ->buttons(
                Html::submitButton($translator->translate('voyti.view.send_button', category: 'voyti'))
            )
?>
    </form>
</div>
