<?php

declare(strict_types=1);
use Yiisoft\FormModel\Field;

use Yiisoft\Html\Html;
use Yiisoft\Html\Tag\Button;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var array $errors
 * @var TranslatorInterface $translator
 */
?>
<div class="voyti-two-factor-sms">
    <h3 class="mb-3"><?= $translator->translate('voyti.view.two_factor_sms.title', category: 'voyti') ?></h3>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $field => $fieldErrors): ?>
                <?php foreach ((array) $fieldErrors as $error): ?>
                    <div><?= Html::encode($error) ?></div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <div class="mb-3">
            <label class="form-label"><?= $translator->translate('voyti.view.two_factor_sms.phone', category: 'voyti') ?></label>
            <input type="text" class="form-control" name="phone" required>
        </div>
        <?= Field::buttonGroup()
            ->buttons(
                Button::submit($translator->translate('voyti.view.two_factor_sms.send', category: 'voyti'))->class('btn', 'btn-primary')
            )
?>
    </form>
</div>
