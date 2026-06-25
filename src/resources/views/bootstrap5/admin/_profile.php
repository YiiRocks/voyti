<?php

declare(strict_types=1);

use YiiRocks\Voyti\Form\Settings\UserProfileForm;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var UserProfileForm $model
 * @var YiiRocks\Voyti\Entity\User $user
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

$this->setTitle($translator->translate('voyti.view.admin.update_profile_title', category: 'voyti'));

echo Html::div()->class('voyti-admin-update-userProfile')->open();
    echo Html::H1($translator->translate('voyti.view.admin.update_profile_title', category: 'voyti'));

    echo Html::form()
        ->post($url->generate('voyti/admin-update-profile', ['id' => $user->getId()]))
        ->csrf($csrf)
        ->open();

    echo Field::errorSummary($model);

    echo Field::text($model, 'name');

    echo Field::textarea($model, 'bio')->rows(3);

    echo Field::email($model, 'publicEmail');

    echo Field::buttonGroup()
        ->buttons(
            Html::submitButton($translator->translate('voyti.view.update_button', category: 'voyti'))
        );

    echo Html::form()->close();
echo Html::div()->close();
