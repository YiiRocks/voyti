<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Strategy;

use YiiRocks\Voyti\Enum\EmailChangeConfirmation;
use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Model\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Service\MailService;

final readonly class EmailChangeStrategyFactory
{
    public function __construct(
        private UserTokenFactory $tokenFactory,
        private MailService $mailService,
    ) {
    }

    public function makeByStrategyType(EmailChangeConfirmation $confirmation, SettingsForm $form): NoneEmailChangeStrategy|NewEmailChangeStrategy|BothEmailChangeStrategy
    {
        return match ($confirmation) {
            EmailChangeConfirmation::NONE => new NoneEmailChangeStrategy($form),
            EmailChangeConfirmation::NEW => new NewEmailChangeStrategy(
                $form,
                $this->tokenFactory,
                $this->mailService,
            ),
            EmailChangeConfirmation::BOTH => new BothEmailChangeStrategy(
                $form,
                $this->tokenFactory,
                $this->mailService,
                new NewEmailChangeStrategy($form, $this->tokenFactory, $this->mailService),
            ),
        };
    }
}
