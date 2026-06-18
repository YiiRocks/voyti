<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Form;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Validator\Helper\ObjectParser;
use Yiisoft\Validator\Rule\Email;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\RulesProviderInterface;
use YiiRocks\Voyti\ModuleConfig;

final class ResendForm extends FormModel implements RulesProviderInterface
{
    #[Required]
    #[Email]
    public string $email = '';

    public string $gRecaptchaResponse = '';

    public function __construct(
        private readonly ModuleConfig $config,
    ) {
    }

    public function getRules(): iterable
    {
        $parser = new ObjectParser($this);
        $rules = $parser->getRules();

        if ($this->config->recaptchaVersion !== null && class_exists(\YiiRocks\Recaptcha\RecaptchaV3Rule::class)) {
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
        ];
    }

    public function getFormName(): string
    {
        return 'resend';
    }
}
