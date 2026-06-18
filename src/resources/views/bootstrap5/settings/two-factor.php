<?php

declare(strict_types=1);
use YiiRocks\Voyti\Entity\User;

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Html\Tag\Button;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var User $user
 * @var string $qrCodeUri
 * @var array $errors
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 */
?>
<div class="voyti-two-factor">
    <h3 class="mb-3"><?= $translator->translate('voyti.view.two_factor.title', category: 'voyti') ?></h3>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $field => $fieldErrors): ?>
                <?php foreach ((array) $fieldErrors as $error): ?>
                    <div><?= Html::encode($error) ?></div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($user->isAuthTfEnabled()): ?>
        <p><?= $translator->translate('voyti.view.two_factor.enabled', category: 'voyti') ?></p>
        <form method="post" action="<?= Html::encode($url->generate('voyti/settings-two-factor-disable')) ?>">
            <?= Field::buttonGroup()
                ->buttons(
                    Button::submit($translator->translate('voyti.view.two_factor.disable', category: 'voyti'))->class('btn', 'btn-danger')
                )
        ?>
        </form>
    <?php else: ?>
        <p><?= $translator->translate('voyti.view.two_factor.scan_qr', category: 'voyti') ?></p>
        <?php if (!empty($qrCodeUri)): ?>
            <img src="<?= Html::encode($qrCodeUri) ?>" alt="QR Code" class="img-fluid mb-3">
        <?php else: ?>
            <div class="alert alert-warning"><?= $translator->translate('voyti.view.two_factor.qr_unavailable', category: 'voyti') ?></div>
        <?php endif; ?>
        <form method="post" action="<?= Html::encode($url->generate('voyti/settings-two-factor-enable')) ?>">
            <div class="mb-3">
                <label class="form-label"><?= $translator->translate('voyti.view.two_factor.enter_code', category: 'voyti') ?></label>
                <input type="text" class="form-control" name="code" required>
            </div>
            <?= Field::buttonGroup()
            ->buttons(
                Button::submit($translator->translate('voyti.view.two_factor.enable', category: 'voyti'))->class('btn', 'btn-primary')
            )
        ?>
        </form>
    <?php endif; ?>
</div>
