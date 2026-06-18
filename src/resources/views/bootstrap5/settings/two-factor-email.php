<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var array $errors
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 */
?>
<div class="voyti-two-factor-email">
    <h3 class="mb-3"><?= $translator->translate('voyti.view.two_factor_email.title') ?></h3>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $field => $fieldErrors): ?>
                <?php foreach ((array) $fieldErrors as $error): ?>
                    <div><?= Html::encode($error) ?></div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <p><?= $translator->translate('voyti.view.two_factor_email.enter_code') ?></p>
    <form method="post">
        <div class="mb-3">
            <input type="text" class="form-control" name="code" required>
        </div>
        <button type="submit" class="btn btn-primary"><?= $translator->translate('voyti.view.two_factor.verify') ?></button>
    </form>
</div>
