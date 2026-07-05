<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Strategy;

use InvalidArgumentException;
use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Service\MailService;

final readonly class EmailChangeStrategyFactory
{
    private const array MAP = [
        MailChangeStrategyInterface::TYPE_INSECURE => InsecureEmailChangeStrategy::class,
        MailChangeStrategyInterface::TYPE_DEFAULT => DefaultEmailChangeStrategy::class,
        MailChangeStrategyInterface::TYPE_SECURE => SecureEmailChangeStrategy::class,
    ];

    public function __construct(
        private UserTokenFactory $tokenFactory,
        private MailService $mailService,
    ) {
    }

    public function makeByStrategyType(int $strategy, SettingsForm $form): InsecureEmailChangeStrategy|DefaultEmailChangeStrategy|SecureEmailChangeStrategy
    {
        return match ($strategy) {
            MailChangeStrategyInterface::TYPE_INSECURE => new InsecureEmailChangeStrategy($form),
            MailChangeStrategyInterface::TYPE_DEFAULT => new DefaultEmailChangeStrategy(
                $form,
                $this->tokenFactory,
                $this->mailService,
            ),
            MailChangeStrategyInterface::TYPE_SECURE => new SecureEmailChangeStrategy(
                $form,
                $this->tokenFactory,
                $this->mailService,
                new DefaultEmailChangeStrategy($form, $this->tokenFactory, $this->mailService),
            ),
            default => throw new InvalidArgumentException('Unknown strategy type'),
        };
    }
}
