<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Strategy;

use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Form\Settings\SettingsForm;
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
        /**
         * @infection-ignore-all ReturnRemoval: the early return when
         *   newStrategy->run() is false is redundant: the next
         *   $user === null check also returns false because the
         *   form's user is never set when run() fails.
         */
        if (!$this->newStrategy->run()) {
            return false;
        }

        $user = $this->form->getUser();
        if ($user === null) {
            // @codeCoverageIgnoreStart
            // Unreachable: newStrategy->run() above already returned false for this exact condition.
            return false;
            // @codeCoverageIgnoreEnd
        }

        /**
         * @infection-ignore-all
         *
         * getUserId() ?? 0 fallback is unreachable — save() already
         * ran in the new-email strategy call, guaranteeing a non-null id.
         */
        $userToken = $this->tokenFactory->makeConfirmOldMailToken((int) ($user->getId() ?? 0));

        if ($this->mailService->sendReconfirmation($user, $userToken)) {
            return true;
        }

        // @codeCoverageIgnoreStart
        // MailService::send() has no failure path in the current implementation; this guards the bool contract.
        return false;
        // @codeCoverageIgnoreEnd
    }
}
