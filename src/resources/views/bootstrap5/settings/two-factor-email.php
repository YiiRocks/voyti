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
<div class="voyti-two-factor-email">
    <h3 class="mb-3"><?= $translator->translate('voyti.view.two_factor_email.title', category: 'voyti') ?></h3>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $field => $fieldErrors): ?>
                <?php foreach ((array) $fieldErrors as $error): ?>
                    <div><?= Html::encode($error) ?></div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <p><?= $translator->translate('voyti.view.two_factor_email.enter_code', category: 'voyti') ?></p>
    <form method="post">
        <div class="mb-3">
            <input type="text" class="form-control" name="code" required>
        </div>
        <?= Field::buttonGroup()
            ->buttons(
                Button::submit($translator->translate('voyti.view.two_factor.verify', category: 'voyti'))->class('btn', 'btn-primary')
            )
?>
    </form>
</div>
