<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Strategy;

use YiiRocks\Voyti\Factory\TokenFactory;
use YiiRocks\Voyti\Factory\MailFactory;
use YiiRocks\Voyti\Form\SettingsForm;

final class SecureEmailChangeStrategy implements MailChangeStrategyInterface
{
    public function __construct(
        private readonly SettingsForm $form,
        private readonly TokenFactory $tokenFactory,
        private readonly MailFactory $mailFactory,
        private readonly DefaultEmailChangeStrategy $defaultStrategy,
    ) {
    }

    public function run(): bool
    {
        if (!$this->defaultStrategy->run()) {
            return false;
        }

        $user = $this->form->getUser();
        $token = $this->tokenFactory->makeConfirmOldMailToken($user->getId() ?? 0);

        if ($this->mailFactory->sendReconfirmation($user, $token)) {
            return $user->save();
        }

        return false;
    }
}
