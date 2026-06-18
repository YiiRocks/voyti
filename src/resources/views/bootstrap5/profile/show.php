<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var YiiRocks\Voyti\Entity\User $user
 * @var YiiRocks\Voyti\Entity\Profile $profile
 * @var \Yiisoft\Translator\TranslatorInterface $translator
 */
?>
<div class="voyti-profile card">
    <div class="card-body">
        <h2 class="card-title"><?= Html::encode($user->getUsername()) ?></h2>
        <p class="card-text"><?= $translator->translate('voyti.view.profile.email_label') ?> <?= Html::encode($user->getEmail()) ?></p>
        <?php if ($profile->getName()): ?>
            <p class="card-text"><?= $translator->translate('voyti.view.profile.name_label') ?> <?= Html::encode($profile->getName()) ?></p>
        <?php endif; ?>
        <?php if ($profile->getLocation()): ?>
            <p class="card-text"><?= $translator->translate('voyti.view.profile.location_label') ?> <?= Html::encode($profile->getLocation()) ?></p>
        <?php endif; ?>
        <?php if ($profile->getBio()): ?>
            <p class="card-text"><?= $translator->translate('voyti.view.profile.bio_label') ?> <?= nl2br(Html::encode($profile->getBio())) ?></p>
        <?php endif; ?>
    </div>
</div>
