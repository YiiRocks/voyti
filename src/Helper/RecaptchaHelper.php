<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Helper;

use Yiisoft\FormModel\FormModelInterface;
use YiiRocks\Voyti\ModuleConfig;

final class RecaptchaHelper
{
    public static function isAvailable(): bool
    {
        return class_exists(\YiiRocks\Recaptcha\RecaptchaV3Field::class)
            || class_exists(\YiiRocks\Recaptcha\RecaptchaV2Field::class);
    }

    public static function render(FormModelInterface $form, ModuleConfig $config, string $attribute = 'gRecaptchaResponse'): string
    {
        if (!self::isAvailable()) {
            return '';
        }

        if ($config->recaptchaVersion === null) {
            return '';
        }

        $formName = $form->getFormName();

        if ($config->recaptchaVersion === 'v2') {
            if (!class_exists(\YiiRocks\Recaptcha\RecaptchaV2Field::class)) {
                return '';
            }
            return \YiiRocks\Recaptcha\RecaptchaV2Field::field($form, $attribute)->render();
        }

        if (!class_exists(\YiiRocks\Recaptcha\RecaptchaV3Field::class)) {
            return '';
        }
        return \YiiRocks\Recaptcha\RecaptchaV3Field::field($form, $attribute)
            ->withAction('voyti_' . $formName)
            ->render();
    }
}
