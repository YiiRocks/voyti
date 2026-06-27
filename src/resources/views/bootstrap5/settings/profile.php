<?php

declare(strict_types=1);

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserProfile;
use YiiRocks\Voyti\Form\Settings\SettingsForm;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var SettingsForm $model
 * @var ModuleConfig $config
 * @var User $user
 * @var UserProfile $userProfile
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var array $errors
 * @var string $csrf
 */

$this->setTitle($translator->translate('voyti.view.settings.title', category: 'voyti'));

echo Html::div()->class('voyti-settings')->open();
include dirname(__DIR__) . '/shared/_menu.php';

echo Html::H1($translator->translate('voyti.view.settings.title', category: 'voyti'));

echo Html::form()
    ->post($url->generate('voyti/settings'))
    ->csrf($csrf)
    ->open();

echo Field::errorSummary(null)->errors($errors);

echo Field::text($model, 'name');

echo Field::email($model, 'publicEmail');

echo Field::textarea($model, 'bio');

echo Field::buttonGroup()
    ->buttons(
        Html::submitButton($translator->translate('voyti.view.save_button', category: 'voyti'))
    );

echo Html::form()->close();
echo Html::div()->close();
