<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service;

use Yiisoft\Aliases\Aliases;
use Yiisoft\Mailer\MailerInterface;
use Yiisoft\Mailer\Message;
use YiiRocks\Voyti\Entity\Token;
use YiiRocks\Voyti\Entity\User;

final class MailService
{
    /**
     * @param array<string, string> $mailParams
     */
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly array $mailParams,
        private readonly Aliases $aliases,
    ) {
    }

    private function getMailSubject(string $key, string $default): string
    {
        $subject = $this->mailParams[$key] ?? $default;
        return str_replace('{app}', '', $subject);
    }

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
            ['user' => $user],
        );
    }

    public function sendConfirmation(User $user, Token $token): bool
    {
        $subject = $this->getMailSubject('confirmationMailSubject', 'Confirm account');
        return $this->send(
            'confirmation',
            $user->getEmail(),
            $subject,
            'confirmation',
            ['user' => $user, 'token' => $token],
        );
    }

    public function sendReconfirmation(User $user, Token $token): bool
    {
        $subject = $this->getMailSubject('reconfirmationMailSubject', 'Confirm email change');
        return $this->send(
            'reconfirmation',
            $user->getEmail(),
            $subject,
            'reconfirmation',
            ['user' => $user, 'token' => $token],
        );
    }

    public function sendRecovery(string $email, Token $token): bool
    {
        $subject = $this->getMailSubject('recoveryMailSubject', 'Password reset');
        return $this->send(
            'recovery',
            $email,
            $subject,
            'recovery',
            ['token' => $token],
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
            ['code' => $code],
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
            ['user' => $user, 'password' => $password],
        );
    }

    /**
     * @return null|string
     */
    private function renderView(string $view, array $params): ?string
    {
        $file = $this->aliases->get('@voytiMail') . '/' . $view;
        if (!file_exists($file)) {
            return null;
        }
        extract($params, EXTR_OVERWRITE);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }
}
