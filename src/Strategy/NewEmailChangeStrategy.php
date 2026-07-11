<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Strategy;

use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Model\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Service\MailService;

final readonly class NewEmailChangeStrategy implements EmailChangeStrategyInterface
{
    public function __construct(
        private SettingsForm $form,
        private UserTokenFactory $tokenFactory,
        private MailService $mailService,
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

        if ($this->mailService->sendReconfirmation($user, $userToken)) {
            $user->save();
            return true;
        }

        // @codeCoverageIgnoreStart
        // MailService::send() has no failure path in the current implementation; this guards the bool contract.
        return false;
        // @codeCoverageIgnoreEnd
    }
}
