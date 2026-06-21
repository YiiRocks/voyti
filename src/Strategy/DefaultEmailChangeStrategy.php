<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Strategy;

use YiiRocks\Voyti\Factory\MailFactory;
use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Form\Settings\SettingsForm;

final class DefaultEmailChangeStrategy implements MailChangeStrategyInterface
{
    public function __construct(
        private readonly SettingsForm $form,
        private readonly UserTokenFactory $tokenFactory,
        private readonly MailFactory $mailFactory,
    ) {
    }

    #[\Override]
    public function run(): bool
    {
        $user = $this->form->getUser();
        if ($user === null) {
            return false;
        }
        $user->setUnconfirmedEmail($this->form->email);

        $userToken = $this->tokenFactory->makeConfirmNewMailToken((int) ($user->getId() ?? 0));

        if ($this->mailFactory->sendReconfirmation($user, $userToken)) {
            $user->save();
            return true;
        }

        return false;
    }
}
