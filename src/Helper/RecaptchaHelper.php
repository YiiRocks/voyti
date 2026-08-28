<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Helper;

use YiiRocks\Recaptcha\RecaptchaRegistry;
use YiiRocks\Recaptcha\RecaptchaV2Field;
use YiiRocks\Recaptcha\RecaptchaV2Rule;
use YiiRocks\Recaptcha\RecaptchaV3Field;
use YiiRocks\Recaptcha\RecaptchaV3Rule;
use YiiRocks\Voyti\Enum\RecaptchaVersion;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\FormModel\FormModelInterface;
use Yiisoft\Validator\RuleInterface;

/**
 * Renders the reCAPTCHA widget and builds its validation rule for a form, based on
 * `VoytiConfig::$recaptchaVersion`. The optional `yiirocks/recaptcha` package is guarded by a
 * single `class_exists(RecaptchaRegistry::class)` check so forms degrade to a no-op when it
 * isn't installed.
 */
final class RecaptchaHelper
{
    public static function render(
        FormModelInterface $form,
        VoytiConfig $config,
        string $attribute = 'gRecaptchaResponse',
    ): string {
        // @infection-ignore-all Optional dependency always installed in test environments
        if (!class_exists(RecaptchaRegistry::class)) {
            // @codeCoverageIgnoreStart
            return '';
            // @codeCoverageIgnoreEnd
        }

        if ($config->recaptchaVersion === RecaptchaVersion::V2) {
            return RecaptchaV2Field::field($form, $attribute)->render();
        }

        return RecaptchaV3Field::field($form, $attribute)
            ->withAction('voyti_' . $form->getFormName())
            ->render();
    }

    /**
     * @return list<RuleInterface>
     */
    public static function rules(VoytiConfig $config, string $formName): array
    {
        // @infection-ignore-all Optional dependency always installed in test environments
        if (!class_exists(RecaptchaRegistry::class)) {
            // @codeCoverageIgnoreStart
            return [];
            // @codeCoverageIgnoreEnd
        }

        $ruleClass = $config->recaptchaVersion === RecaptchaVersion::V2
            ? RecaptchaV2Rule::class
            : RecaptchaV3Rule::class;

        $params = [];
        if ($config->recaptchaVersion === RecaptchaVersion::V3) {
            $params['threshold'] = 0.5;
            $params['action'] = 'voyti_' . $formName;
        }

        return [new $ruleClass(...$params)];
    }
}
