<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var array $errors
 * @var \Yiisoft\Translator\TranslatorInterface $translator
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
        <button type="submit" class="btn btn-primary"><?= $translator->translate('voyti.view.two_factor_sms.send', category: 'voyti') ?></button>
    </form>
</div>
