<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Strategy;

use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Model\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Service\MailService;

final readonly class BothEmailChangeStrategy implements EmailChangeStrategyInterface
{
    public function __construct(
        private SettingsForm $form,
        private UserTokenFactory $tokenFactory,
        private MailService $mailService,
        private NewEmailChangeStrategy $newStrategy,
    ) {
    }

    #[\Override]
    public function run(): bool
    {
        if (!$this->newStrategy->run()) {
            return false;
        }

        $user = $this->form->getUser();
        // zend.assertions=-1 strips this statement at compile time, so it can never register as executed.
        // @codeCoverageIgnoreStart
        assert($user !== null);
        // @codeCoverageIgnoreEnd

        $userToken = $this->tokenFactory->makeConfirmOldMailToken((int) $user->getId());

        if ($this->mailService->sendReconfirmation($user, $userToken)) {
            return true;
        }

        // @codeCoverageIgnoreStart
        // MailService::send() has no failure path in the current implementation; this guards the bool contract.
        return false;
        // @codeCoverageIgnoreEnd
    }
}
