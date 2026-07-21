<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Model\Form\Auth;

use Override;
use YiiRocks\Voyti\Helper\RecaptchaHelper;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Helper\ObjectParser;
use Yiisoft\Validator\LabelsProviderInterface;
use Yiisoft\Validator\Rule\Email;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\RulesProviderInterface;

/**
 * Backs the "resend confirmation email" page.
 */
final class ResendForm extends FormModel implements LabelsProviderInterface, RulesProviderInterface
{
    #[Required]
    #[Email(checkDns: true, enableIdn: true, skipOnEmpty: true)]
    #[Length(max: 255)]
    public string $email = '';

    public string $gRecaptchaResponse = '';

    public function __construct(
        private readonly ModuleConfig $config,
        private readonly TranslatorInterface $translator,
    ) {}

    /**
     * @return string
     *
     * @psalm-return 'resend'
     */
    #[Override]
    public function getFormName(): string
    {
        return 'resend';
    }

    /**
     * @return string[]
     *
     * @psalm-return array{email: string}
     */
    #[Override]
    public function getPropertyLabels(): array
    {
        return [
            'email' => $this->translator->translate('voyti.view.email_label', category: 'voyti'),
        ];
    }

    #[Override]
    public function getRules(): iterable
    {
        $parser = new ObjectParser($this);
        $rules = $parser->getRules();

        $recaptchaRules = RecaptchaHelper::rules($this->config, $this->getFormName());
        if ($recaptchaRules !== []) {
            $rules['gRecaptchaResponse'] = $recaptchaRules;
        }

        return $rules;
    }

    #[Override]
    public function getValidationPropertyLabels(): array
    {
        return $this->getPropertyLabels();
    }
}
