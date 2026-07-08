<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Strategy;

use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Service\MailService;

final readonly class SecureEmailChangeStrategy implements MailChangeStrategyInterface
{
    public function __construct(
        private SettingsForm $form,
        private UserTokenFactory $tokenFactory,
        private MailService $mailService,
        private DefaultEmailChangeStrategy $defaultStrategy,
    ) {
    }

    #[\Override]
    public function run(): bool
    {
        /**
         * @infection-ignore-all ReturnRemoval: the early return when
         *   defaultStrategy->run() is false is redundant: the next
         *   $user === null check also returns false because the
         *   form's user is never set when run() fails.
         */
        if (!$this->defaultStrategy->run()) {
            return false;
        }

        $user = $this->form->getUser();
        if ($user === null) {
            return false;
        }

        /**
         * @infection-ignore-all
         *
         * getUserId() ?? 0 fallback is unreachable — save() already
         * ran in the default strategy call, guaranteeing a non-null id.
         */
        $userToken = $this->tokenFactory->makeConfirmOldMailToken((int) ($user->getId() ?? 0));

        if ($this->mailService->sendReconfirmation($user, $userToken)) {
            return true;
        }

        return false;
    }
}
