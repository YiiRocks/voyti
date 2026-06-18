<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service;

use Psr\EventDispatcher\EventDispatcherInterface;
use Yiisoft\Translator\TranslatorInterface;
use YiiRocks\Voyti\Entity\Token;
use YiiRocks\Voyti\Event\FormEvent;
use YiiRocks\Voyti\Helper\SecurityHelper;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserRepository;

final class PasswordRecoveryService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MailService $mailService,
        private readonly SecurityHelper $securityHelper,
        private readonly ModuleConfig $config,
        private readonly TranslatorInterface $translator,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function run(string $email): ServiceResult
    {
        $user = $this->userRepository->findByEmail($email);
        if ($user === null) {
            return ServiceResult::success($this->translator->translate('voyti.recovery.message_sent_if_exists'));
        }

        if ($user->isBlocked()) {
            return ServiceResult::success($this->translator->translate('voyti.recovery.message_sent_if_exists'));
        }

        $token = new Token();
        $token->setUserId($user->getId() !== null ? (int) $user->getId() : 0);
        $token->setType(Token::TYPE_RECOVERY);
        $token->setCreatedAt(time());
        $token->setCode($this->securityHelper->generateRandomString(32));
        $token->save();

        $this->mailService->sendRecovery($email, $token);

        return ServiceResult::success($this->translator->translate('voyti.recovery.message_sent'));
    }
}
