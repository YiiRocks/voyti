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
        if (!$this->defaultStrategy->run()) {
            return false;
        }

        $user = $this->form->getUser();
        if ($user === null) {
            return false;
        }
        $userToken = $this->tokenFactory->makeConfirmOldMailToken((int) ($user->getId() ?? 0));

        if ($this->mailService->sendReconfirmation($user, $userToken)) {
            $user->save();
            return true;
        }

        return false;
    }
}
