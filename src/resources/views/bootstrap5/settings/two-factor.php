<?php

declare(strict_types=1);

use YiiRocks\Voyti\Entity\User;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var User $user
 * @var string $qrCodeUri
 * @var array $errors
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

echo Html::div()->class('voyti-two-factor')->open();
    Html::H3()->class('mb-3')->open();
        echo $translator->translate('voyti.view.two_factor.title', category: 'voyti');
    echo Html::H3()->close();

    if (!empty($errors)) {
        echo Html::div()->class('alert alert-danger')->open();
            foreach ($errors as $field => $fieldErrors) {
                foreach ((array) $fieldErrors as $error) {
                    echo Html::div(Html::encode($error));
                }
            }
        echo Html::div()->close();
    }

    if ($user->isAuthTfEnabled()) {
        echo Html::p($translator->translate('voyti.view.two_factor.enabled', category: 'voyti'));

        echo Html::form()
            ->post($url->generate('voyti/settings-two-factor-disable'))
            ->csrf($csrf)
            ->open();

        echo Field::buttonGroup()
            ->buttons(
                Html::submitButton($translator->translate('voyti.view.two_factor.disable', category: 'voyti'))->class('btn', 'btn-danger')
            );

        echo Html::form()->close();
    } else {
        echo Html::p($translator->translate('voyti.view.two_factor.scan_qr', category: 'voyti'));

        if (!empty($qrCodeUri)) {
            echo Html::img($qrCodeUri)->alt('QR Code')->class('img-fluid mb-3');
        } else {
            echo Html::div()->class('alert alert-warning')->open();
                echo $translator->translate('voyti.view.two_factor.qr_unavailable', category: 'voyti');
            echo Html::div()->close();
        }

        echo Html::form()
            ->post($url->generate('voyti/settings-two-factor-enable'))
            ->csrf($csrf)
            ->open();

        echo '<div class="mb-3">' . "\n";
            echo '    <label class="form-label">' . $translator->translate('voyti.view.two_factor.enter_code', category: 'voyti') . '</label>' . "\n";
            echo '    <input type="text" class="form-control" name="code" required>' . "\n";
        echo '</div>' . "\n";

        echo Field::buttonGroup()
            ->buttons(
                Html::submitButton($translator->translate('voyti.view.two_factor.enable', category: 'voyti'))->class('btn', 'btn-primary')
            );

        echo Html::form()->close();
    }
echo Html::div()->close();
