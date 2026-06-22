<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Password;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Security\Random;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Service\ServiceResult;
use Yiisoft\Translator\TranslatorInterface;

final class RecoveryService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MailService $mailService,
        private readonly ModuleConfig $config,
        private readonly TranslatorInterface $translator,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function run(string $email): ServiceResult
    {
        $user = $this->userRepository->findByEmail($email);
        if ($user === null) {
            return ServiceResult::success($this->translator->translate('voyti.recovery.message_sent_if_exists', category: 'voyti'));
        }

        if ($user->isBlocked()) {
            return ServiceResult::success($this->translator->translate('voyti.recovery.message_sent_if_exists', category: 'voyti'));
        }

        $userToken = new UserToken();
        $userToken->setUserId($user->getId() !== null ? (int) $user->getId() : 0);
        $userToken->setType(UserToken::TYPE_RECOVERY);
        $userToken->setCreatedAt(time());
        $userToken->setCode(Random::string(32));
        $userToken->save();

        $this->mailService->sendRecovery($email, $userToken);

        return ServiceResult::success($this->translator->translate('voyti.recovery.message_sent', category: 'voyti'));
    }
}
