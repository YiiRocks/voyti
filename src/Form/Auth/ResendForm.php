<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Form\Auth;

use YiiRocks\Recaptcha\RecaptchaV2Rule;
use YiiRocks\Recaptcha\RecaptchaV3Rule;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Helper\ObjectParser;
use Yiisoft\Validator\Rule\Email;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\RulesProviderInterface;

final class ResendForm extends FormModel implements RulesProviderInterface
{
    #[Required]
    #[Email(checkDns: true, enableIdn: true, skipOnEmpty: true)]
    #[Length(max: 255)]
    public string $email = '';

    public string $gRecaptchaResponse = '';

    public function __construct(
        private readonly ModuleConfig $config,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return string[]
     *
     * @psalm-return array{email: string}
     */
    public function getAttributeLabels(): array
    {
        return [
            'email' => $this->translator->translate('voyti.view.email_label', category: 'voyti'),
        ];
    }

    /**
     * @return string
     *
     * @psalm-return 'resend'
     */
    #[\Override]
    public function getFormName(): string
    {
        return 'resend';
    }

    #[\Override]
    public function getRules(): iterable
    {
        $parser = new ObjectParser($this);
        $rules = $parser->getRules();

        if ($this->config->recaptchaVersion !== null && class_exists(RecaptchaV3Rule::class)) {
            $ruleClass = $this->config->recaptchaVersion === 'v2'
                ? RecaptchaV2Rule::class
                : RecaptchaV3Rule::class;

            $params = [];
            if ($this->config->recaptchaVersion === 'v3') {
                $params['threshold'] = 0.5;
                $params['action'] = 'voyti_' . $this->getFormName();
            }

            $rules['gRecaptchaResponse'] = [new $ruleClass(...$params)];
        }

        return $rules;
    }
}
