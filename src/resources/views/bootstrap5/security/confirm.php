<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var YiiRocks\Voyti\Form\Auth\LoginForm $model
 * @var YiiRocks\Voyti\ModuleConfig $config
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 */
?>
<?php $this->setTitle($translator->translate('voyti.view.two_factor.title', category: 'voyti')); ?>
<div class="voyti-2fa">
    <h1><?= $translator->translate('voyti.view.two_factor.title', category: 'voyti') ?></h1>
    <form action="<?= Html::encode($url->generate('voyti/confirm')) ?>" method="post" novalidate>
        <?= Field::text($model, 'twoFactorAuthenticationCode')->inputAttributes(['autocomplete' => 'one-time-code']) ?>
        <?= Field::buttonGroup()
            ->buttons(
                Html::submitButton($translator->translate('voyti.view.two_factor.verify_button', category: 'voyti'))
            )
?>
    </form>
</div>
