<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Form;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Validator\Helper\ObjectParser;
use Yiisoft\Validator\Rule\Email;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\RulesProviderInterface;
use YiiRocks\Voyti\ModuleConfig;

final class RecoveryForm extends FormModel implements RulesProviderInterface
{
    public const SCENARIO_REQUEST = 'request';
    public const SCENARIO_RESET = 'reset';

    #[Required]
    #[Email]
    public string $email = '';

    #[Required]
    #[Length(min: 6, max: 72)]
    public string $password = '';

    public string $gRecaptchaResponse = '';

    public function __construct(
        private readonly ModuleConfig $config,
        public readonly string $scenario = self::SCENARIO_REQUEST,
    ) {
    }

    public function getRules(): iterable
    {
        $parser = new ObjectParser($this);
        $rules = $parser->getRules();

        if ($this->config->recaptchaVersion !== null
            && $this->scenario === self::SCENARIO_REQUEST
            && class_exists(\YiiRocks\Recaptcha\RecaptchaV3Rule::class)
        ) {
            $ruleClass = $this->config->recaptchaVersion === 'v2'
                ? \YiiRocks\Recaptcha\RecaptchaV2Rule::class
                : \YiiRocks\Recaptcha\RecaptchaV3Rule::class;

            $params = $this->config->recaptchaVersion === 'v3'
                ? ['threshold' => 0.5, 'action' => 'voyti_' . $this->getFormName()]
                : [];

            $rules['gRecaptchaResponse'] ??= [];
            $rules['gRecaptchaResponse'][] = new $ruleClass(...$params);
        }

        return $rules;
    }

    public function getAttributeLabels(): array
    {
        return [
            'email' => 'Email',
            'password' => 'Password',
        ];
    }

    public function getFormName(): string
    {
        return 'recovery';
    }
}
