<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Strategy;

use YiiRocks\Voyti\Factory\MailFactory;
use YiiRocks\Voyti\Factory\TokenFactory;
use YiiRocks\Voyti\Form\Settings\SettingsForm;

final class DefaultEmailChangeStrategy implements MailChangeStrategyInterface
{
    public function __construct(
        private readonly SettingsForm $form,
        private readonly TokenFactory $tokenFactory,
        private readonly MailFactory $mailFactory,
    ) {
    }

    /**
     * @return false|null
     */
    #[\Override]
    public function run(): bool|null
    {
        $user = $this->form->getUser();
        $user->setUnconfirmedEmail($this->form->email);

        $token = $this->tokenFactory->makeConfirmNewMailToken($user->getId() ?? 0);

        if ($this->mailFactory->sendReconfirmation($user, $token)) {
            return $user->save();
        }

        return false;
    }
}
