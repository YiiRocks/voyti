<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Strategy;

use InvalidArgumentException;
use YiiRocks\Voyti\Factory\MailFactory;
use YiiRocks\Voyti\Factory\TokenFactory;
use YiiRocks\Voyti\Form\Settings\SettingsForm;

final class EmailChangeStrategyFactory
{
    private const MAP = [
        MailChangeStrategyInterface::TYPE_INSECURE => InsecureEmailChangeStrategy::class,
        MailChangeStrategyInterface::TYPE_DEFAULT => DefaultEmailChangeStrategy::class,
        MailChangeStrategyInterface::TYPE_SECURE => SecureEmailChangeStrategy::class,
    ];

    public function __construct(
        private readonly TokenFactory $tokenFactory,
        private readonly MailFactory $mailFactory,
    ) {
    }

    public function makeByStrategyType(int $strategy, SettingsForm $form): MailChangeStrategyInterface
    {
        return match ($strategy) {
            MailChangeStrategyInterface::TYPE_INSECURE => new InsecureEmailChangeStrategy($form),
            MailChangeStrategyInterface::TYPE_DEFAULT => new DefaultEmailChangeStrategy(
                $form,
                $this->tokenFactory,
                $this->mailFactory,
            ),
            MailChangeStrategyInterface::TYPE_SECURE => new SecureEmailChangeStrategy(
                $form,
                $this->tokenFactory,
                $this->mailFactory,
                new DefaultEmailChangeStrategy($form, $this->tokenFactory, $this->mailFactory),
            ),
            default => throw new InvalidArgumentException('Unknown strategy type'),
        };
    }
}
