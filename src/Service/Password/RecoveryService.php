<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Password;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Service\ServiceResult;
use Yiisoft\Translator\TranslatorInterface;

final readonly class RecoveryService
{
    public function __construct(
        private UserTokenFactory $userTokenFactory,
        private MailService $mailService,
        private ModuleConfig $config,
        private TranslatorInterface $translator,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function run(string $email): ServiceResult
    {
        $user = User::findByEmail($email);
        if ($user === null) {
            return ServiceResult::success($this->translator->translate('voyti.recovery.message_sent_if_exists', category: 'voyti'));
        }

        if ($user->isBlocked()) {
            return ServiceResult::success($this->translator->translate('voyti.recovery.message_sent_if_exists', category: 'voyti'));
        }

        $userToken = $this->userTokenFactory->makeRecoveryToken((int) $user->getId());

        $this->mailService->sendRecovery($user->getUsername(), $email, $userToken);

        return ServiceResult::success($this->translator->translate('voyti.recovery.message_sent', category: 'voyti'));
    }
}
