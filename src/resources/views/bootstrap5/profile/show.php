<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var YiiRocks\Voyti\Entity\User $user
 * @var YiiRocks\Voyti\Entity\Profile $profile
 * @var TranslatorInterface $translator
 */

echo Html::div()->class('voyti-profile card')->open();
    echo Html::div()->class('card-body')->open();
        echo Html::H1(Html::encode($user->getUsername()));

        echo Html::p()->class('card-text')->open();
            echo $translator->translate('voyti.view.profile.email_label', category: 'voyti') . ' ' . Html::encode($user->getEmail());
        echo Html::p()->close();

        if ($profile->getName()) {
            echo Html::p()->class('card-text')->open();
                echo $translator->translate('voyti.view.profile.name_label', category: 'voyti') . ' ' . Html::encode($profile->getName());
            echo Html::p()->close();
        }

        if ($profile->getLocation()) {
            echo Html::p()->class('card-text')->open();
                echo $translator->translate('voyti.view.profile.location_label', category: 'voyti') . ' ' . Html::encode($profile->getLocation());
            echo Html::p()->close();
        }

        if ($profile->getBio()) {
            echo Html::p()->class('card-text')->open();
                echo $translator->translate('voyti.view.profile.bio_label', category: 'voyti') . ' ' . nl2br(Html::encode($profile->getBio()));
            echo Html::p()->close();
        }
    echo Html::div()->close();
echo Html::div()->close();
