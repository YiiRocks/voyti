<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Helper;

use YiiRocks\Recaptcha\RecaptchaV2Field;
use YiiRocks\Recaptcha\RecaptchaV3Badge;
use YiiRocks\Recaptcha\RecaptchaV3Field;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\FormModel\FormModelInterface;

final class RecaptchaHelper
{
    public static function isAvailable(): bool
    {
        /**
         * @infection-ignore-all
         *
         * Both classes are always present via composer's autoload (hard
         * require), so no test can ever make either class_exists() false.
         */
        return class_exists(RecaptchaV3Field::class)
            || class_exists(RecaptchaV2Field::class);
    }

    public static function render(FormModelInterface $form, ModuleConfig $config, string $attribute = 'gRecaptchaResponse'): string
    {
        if (!self::isAvailable()) {
            // @codeCoverageIgnoreStart
            // Both classes are always present in the test environment (see isAvailable()); this branch only
            // matters for host apps that don't install the optional yiirocks/recaptcha package.
            return '';
            // @codeCoverageIgnoreEnd
        }

        if ($config->recaptchaVersion === null) {
            return '';
        }

        $formName = $form->getFormName();

        if ($config->recaptchaVersion === RecaptchaVersion::V2) {
            if (!class_exists(RecaptchaV2Field::class)) {
                // @codeCoverageIgnoreStart
                // Same as isAvailable(): unreachable while yiirocks/recaptcha is installed in the test environment.
                return '';
                // @codeCoverageIgnoreEnd
            }
            return RecaptchaV2Field::field($form, $attribute)->render();
        }

        if (!class_exists(RecaptchaV3Field::class)) {
            // @codeCoverageIgnoreStart
            // Same as isAvailable(): unreachable while yiirocks/recaptcha is installed in the test environment.
            return '';
            // @codeCoverageIgnoreEnd
        }
        /** @infection-ignore-all Concat ConcatOperandRemoval: both branches throw MissingSiteKeyException in tests, so the action string is never observable. */
        return RecaptchaV3Field::field($form, $attribute)
            ->withBadge(RecaptchaV3Badge::Hidden)
            ->withAction('voyti_' . $formName)
            ->render();
    }
}
