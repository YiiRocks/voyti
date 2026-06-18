<?php

declare(strict_types=1);

use YiiRocks\Voyti\Helper\RecaptchaHelper;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var YiiRocks\Voyti\Form\Auth\RecoveryForm $model
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var array $errors
 */
?>
<?php $this->setTitle($translator->translate('voyti.view.recovery.request_title', category: 'voyti')); ?>
<div class="voyti-recovery">
    <h1><?= $translator->translate('voyti.view.recovery.request_title', category: 'voyti') ?></h1>
    <form action="<?= Html::encode($url->generate('voyti/forgot')) ?>" method="post" novalidate>
        <?= Field::errorSummary(null)->errors($errors) ?>
        <?= Field::email($model, 'email') ?>
        <?= RecaptchaHelper::render($model, $config) ?>
        <?= Field::buttonGroup()
            ->buttons(
                Html::submitButton($translator->translate('voyti.view.recovery.send_link_button', category: 'voyti'))
            )
?>
        <p class="mt-3"><a href="<?= Html::encode($url->generate('voyti/login')) ?>"><?= $translator->translate('voyti.view.recovery.back_to_login', category: 'voyti') ?></a></p>
    </form>
</div>
