<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Model\Form\Settings;

use YiiRocks\Voyti\Helper\TimezoneHelper;
use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\Rule\Callback;
use Yiisoft\Validator\Rule\Email;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Url;

final class UserProfileForm extends FormModel
{
    #[Length(max: 65535)]
    public string $bio = '';
    #[Email(checkDns: true, enableIdn: true, skipOnEmpty: true)]
    #[Length(max: 255)]
    public string $gravatarEmail = '';
    #[Length(max: 255)]
    public string $location = '';
    #[Length(max: 255)]
    public string $name = '';
    #[Email(checkDns: true, enableIdn: true, skipOnEmpty: true)]
    #[Length(max: 255)]
    public string $publicEmail = '';
    #[Callback(method: 'validateTimezone', skipOnEmpty: true)]
    public string $timezone = '';
    #[Length(max: 255)]
    #[Url(skipOnEmpty: true)]
    public string $website = '';

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return string[]
     *
     * @psalm-return array{name: string, publicEmail: string, gravatarEmail: string, location: string, website: string, timezone: string, bio: string}
     */
    public function getAttributeLabels(): array
    {
        return [
            'name' => $this->translator->translate('voyti.view.name_label', category: 'voyti'),
            'publicEmail' => $this->translator->translate('voyti.view.public_email_label', category: 'voyti'),
            'gravatarEmail' => $this->translator->translate('voyti.view.gravatar_email_label', category: 'voyti'),
            'location' => $this->translator->translate('voyti.view.location_label', category: 'voyti'),
            'website' => $this->translator->translate('voyti.view.website_label', category: 'voyti'),
            'timezone' => $this->translator->translate('voyti.view.timezone_label', category: 'voyti'),
            'bio' => $this->translator->translate('voyti.view.bio_label', category: 'voyti'),
        ];
    }

    /**
     * @return string
     *
     * @psalm-return 'userProfile'
     */
    #[\Override]
    public function getFormName(): string
    {
        return 'userProfile';
    }

    #[\Override]
    public function getPropertyLabels(): array
    {
        return $this->getAttributeLabels();
    }

    public function validateTimezone(mixed $value): Result
    {
        $result = new Result();
        if (!TimezoneHelper::isValid((string) $value)) {
            $result->addError("'{$value}' is not a valid timezone identifier.");
        }
        return $result;
    }
}
