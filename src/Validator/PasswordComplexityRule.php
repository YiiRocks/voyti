<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Validator;

use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Rule\Regex;

/**
 * Builds the {@see Regex} validation rule enforcing password complexity (upper/lower/digit/symbol)
 * when {@see ModuleConfig::$enablePasswordComplexity} is enabled.
 */
final class PasswordComplexityRule
{
    /**
     * @return list<Regex>
     */
    public static function rules(ModuleConfig $config, TranslatorInterface $translator): array
    {
        if (!$config->enablePasswordComplexity) {
            return [];
        }

        return [
            new Regex(
                pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).+$/',
                message: $translator->translate('voyti.validator.password_complexity', category: 'voyti'),
            ),
        ];
    }
}
