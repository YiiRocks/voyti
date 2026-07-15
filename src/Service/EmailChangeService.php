<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service;

use YiiRocks\Voyti\Enum\EmailChangeConfirmation;
use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Model\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\ModuleConfig;

final readonly class EmailChangeService
{
    public function __construct(
        private ModuleConfig $config,
        private UserTokenFactory $tokenFactory,
        private MailService $mailService,
    ) {
    }

    public function initiate(EmailChangeConfirmation $confirmation, SettingsForm $form): bool
    {
        $user = $form->getUser();
        if ($user === null) {
            return false;
        }

        return match ($confirmation) {
            EmailChangeConfirmation::NONE => $this->initiateNone($user, $form),
            EmailChangeConfirmation::NEW => $this->initiateNew($user, $form),
            EmailChangeConfirmation::BOTH => $this->initiateBoth($user, $form),
        };
    }

    public function run(string $code, User $user): bool|null
    {
        $userToken = UserToken::findByUserIdAndCode(
            $user->getIdOrZero(),
            $code,
        );

        if ($userToken === null || !in_array($userToken->getType(), [UserToken::TYPE_CONFIRM_NEW_EMAIL, UserToken::TYPE_CONFIRM_OLD_EMAIL], true)) {
            return false;
        }

        if ($userToken->getIsExpired($this->config->tokenConfirmationLifespan)) {
            $userToken->delete();
            return false;
        }

        $userToken->delete();

        if ($user->getUnconfirmedEmail() === null) {
            return false;
        }

        $existingUser = User::findByEmail($user->getUnconfirmedEmail());
        if ($existingUser !== null) {
            return false;
        }

        if ($this->config->emailChangeConfirmation === EmailChangeConfirmation::BOTH) {
            if ($userToken->getType() === UserToken::TYPE_CONFIRM_NEW_EMAIL) {
                $user->setFlags($user->getFlags() | User::NEW_EMAIL_CONFIRMED);
                $user->save();
                return true;
            }
            if ($userToken->getType() === UserToken::TYPE_CONFIRM_OLD_EMAIL) {
                $user->setFlags($user->getFlags() | User::OLD_EMAIL_CONFIRMED);
            }
        }

        if (
            ($this->config->emailChangeConfirmation === EmailChangeConfirmation::NEW)
            || ($user->getFlags() & User::NEW_EMAIL_CONFIRMED && $user->getFlags() & User::OLD_EMAIL_CONFIRMED)
        ) {
            $user->setEmail($user->getUnconfirmedEmail());
            $user->setUnconfirmedEmail(null);
            $user->setFlags(0);
            $user->setUpdatedAt(time());
        }

        $user->save();
        return true;
    }

    private function initiateBoth(User $user, SettingsForm $form): bool
    {
        if (!$this->initiateNew($user, $form)) {
            return false;
        }

        $userToken = $this->tokenFactory->makeConfirmOldMailToken((int) $user->getId());

        if ($this->mailService->sendReconfirmation($user, $userToken)) {
            return true;
        }

        // @codeCoverageIgnoreStart
        // MailService::send() has no failure path in the current implementation; this guards the bool contract.
        return false;
        // @codeCoverageIgnoreEnd
    }

    private function initiateNew(User $user, SettingsForm $form): bool
    {
        $user->setUnconfirmedEmail($form->email);
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

    private function initiateNone(User $user, SettingsForm $form): bool
    {
        $user->setEmail($form->email);
        $user->save();
        return true;
    }
}
