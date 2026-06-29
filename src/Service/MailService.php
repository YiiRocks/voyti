<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service;

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserToken;
use Yiisoft\Mailer\MailerInterface;
use Yiisoft\Mailer\Message;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

final class MailService
{
    /**
     * @param array<string, string> $mailParams
     */
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $mailPath,
        private readonly array $mailParams,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $url,
        private readonly string $appName = 'Voyti',
    ) {
    }

    /**
     * @return true
     */
    public function send(string $type, string $to, string $subject, string $view, array $params = []): bool
    {
        $from = $this->mailParams['fromEmail'] ?? 'no-reply@example.com';

        $message = new Message(
            from: $from,
            to: $to,
            subject: $subject,
        );

        $htmlBody = $this->renderView("{$view}.php", $params);
        $textBody = $this->renderView("text/{$view}.php", $params);

        if ($htmlBody !== null) {
            $message = $message->withHtmlBody($htmlBody);
        }
        if ($textBody !== null) {
            $message = $message->withTextBody($textBody);
        }

        $this->mailer->send($message);
        return true;
    }

    public function sendAdminNotification(string $adminEmail, User $user): bool
    {
        return $this->send(
            'admin_notification',
            $adminEmail,
            'New user registration',
            'welcome',
            [
                'username' => $user->getUsername(),
                'translator' => $this->translator,
            ],
        );
    }

    public function sendConfirmation(User $user, UserToken $userToken): bool
    {
        $subject = $this->getMailSubject('confirmationMailSubject', 'Confirm account');
        $userId = $user->getId();
        if ($userId === null) {
            return false;
        }
        return $this->send(
            'confirmation',
            $user->getEmail(),
            $subject,
            'confirmation',
            [
                'username' => $user->getUsername(),
                'confirmationUrl' => $this->url->generateAbsolute('voyti/registration-confirm', ['id' => $userId, 'code' => $userToken->getCode()]),
                'translator' => $this->translator,
            ],
        );
    }

    public function sendReconfirmation(User $user, UserToken $userToken): bool
    {
        $subject = $this->getMailSubject('reconfirmationMailSubject', 'Confirm email change');
        return $this->send(
            'reconfirmation',
            $user->getEmail(),
            $subject,
            'reconfirmation',
            [
                'username' => $user->getUsername(),
                'confirmationUrl' => $this->url->generateAbsolute('voyti/settings-confirm', ['code' => $userToken->getCode()]),
                'translator' => $this->translator,
            ],
        );
    }

    public function sendRecovery(string $username, string $email, UserToken $userToken): bool
    {
        $subject = $this->getMailSubject('recoveryMailSubject', 'Password reset');
        return $this->send(
            'recovery',
            $email,
            $subject,
            'recovery',
            [
                'username' => $username,
                'recoveryUrl' => $this->url->generateAbsolute('voyti/recover', ['id' => $userToken->getUserId(), 'code' => $userToken->getCode()]),
                'translator' => $this->translator,
            ],
        );
    }

    public function sendTwoFactorCode(string $email, string $code): bool
    {
        $subject = $this->getMailSubject('twoFactorMailSubject', '2FA Code');
        return $this->send(
            'twofactor',
            $email,
            $subject,
            'twofactorcode',
            [
                'code' => $code,
                'translator' => $this->translator,
            ],
        );
    }

    public function sendWelcome(User $user, string $password): bool
    {
        $subject = $this->getMailSubject('welcomeMailSubject', 'Welcome');
        return $this->send(
            'welcome',
            $user->getEmail(),
            $subject,
            'welcome',
            [
                'username' => $user->getUsername(),
                'translator' => $this->translator,
            ],
        );
    }

    private function getMailSubject(string $key, string $default): string
    {
        $subject = $this->mailParams[$key] ?? $default;
        return trim(str_replace('{app}', $this->appName, $subject));
    }

    /**
     * @return null|string
     */
    private function renderView(string $view, array $params): ?string
    {
        $file = $this->mailPath . '/' . $view;
        if (!file_exists($file)) {
            return null;
        }
        extract($params, EXTR_OVERWRITE);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }
}
