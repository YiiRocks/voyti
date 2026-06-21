<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var YiiRocks\Voyti\Entity\User $user
 * @var YiiRocks\Voyti\Entity\UserProfile $userProfile
 * @var TranslatorInterface $translator
 */

echo Html::div()->class('voyti-userProfile card')->open();
    echo Html::div()->class('card-body')->open();
        echo Html::H1(Html::encode($user->getUsername()));

        echo Html::p()->class('card-text')->open();
            echo $translator->translate('voyti.view.userProfile.email_label', category: 'voyti') . ' ' . Html::encode($user->getEmail());
        echo Html::p()->close();

        if ($userProfile->getName()) {
            echo Html::p()->class('card-text')->open();
                echo $translator->translate('voyti.view.userProfile.name_label', category: 'voyti') . ' ' . Html::encode($userProfile->getName());
            echo Html::p()->close();
        }

        if ($userProfile->getLocation()) {
            echo Html::p()->class('card-text')->open();
                echo $translator->translate('voyti.view.userProfile.location_label', category: 'voyti') . ' ' . Html::encode($userProfile->getLocation());
            echo Html::p()->close();
        }

        if ($userProfile->getBio()) {
            echo Html::p()->class('card-text')->open();
                echo $translator->translate('voyti.view.userProfile.bio_label', category: 'voyti') . ' ' . nl2br(Html::encode($userProfile->getBio()));
            echo Html::p()->close();
        }
    echo Html::div()->close();
echo Html::div()->close();
