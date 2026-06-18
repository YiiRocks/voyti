<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service;

use Yiisoft\Aliases\Aliases;
use Yiisoft\Mail\MailerInterface;
use Yiisoft\Mail\Message;

final class MailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly array $mailParams,
        private readonly Aliases $aliases,
    ) {
    }

    public function send(string $type, string $to, string $subject, string $view, array $params = []): bool
    {
        $from = $this->mailParams['fromEmail'] ?? 'no-reply@example.com';

        $message = (new Message())
            ->from($from)
            ->to($to)
            ->subject($subject);

        $htmlBody = $this->renderView("{$view}.php", $params);
        $textBody = $this->renderView("text/{$view}.php", $params);

        if ($htmlBody !== null) {
            $message = $message->html($htmlBody);
        }
        if ($textBody !== null) {
            $message = $message->text($textBody);
        }

        $this->mailer->send($message);
        return true;
    }

    public function sendWelcome(User $user, string $password): bool
    {
        return $this->send(
            'welcome',
            $user->getEmail(),
            str_replace('{app}', '', $this->mailParams['welcomeMailSubject'] ?? 'Welcome'),
            'welcome',
            ['user' => $user, 'password' => $password],
        );
    }

    public function sendConfirmation(User $user, Token $token): bool
    {
        return $this->send(
            'confirmation',
            $user->getEmail(),
            str_replace('{app}', '', $this->mailParams['confirmationMailSubject'] ?? 'Confirm account'),
            'confirmation',
            ['user' => $user, 'token' => $token],
        );
    }

    public function sendRecovery(string $email, Token $token): bool
    {
        return $this->send(
            'recovery',
            $email,
            str_replace('{app}', '', $this->mailParams['recoveryMailSubject'] ?? 'Password reset'),
            'recovery',
            ['token' => $token],
        );
    }

    public function sendReconfirmation(User $user, Token $token): bool
    {
        return $this->send(
            'reconfirmation',
            $user->getEmail(),
            str_replace('{app}', '', $this->mailParams['reconfirmationMailSubject'] ?? 'Confirm email change'),
            'reconfirmation',
            ['user' => $user, 'token' => $token],
        );
    }

    public function sendTwoFactorCode(string $email, string $code): bool
    {
        return $this->send(
            'twofactor',
            $email,
            str_replace('{app}', '', $this->mailParams['twoFactorMailSubject'] ?? '2FA Code'),
            'twofactorcode',
            ['code' => $code],
        );
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

    private function renderView(string $view, array $params): ?string
    {
        $file = $this->aliases->get('@voytiMail') . '/' . $view;
        if (!file_exists($file)) {
            return null;
        }
        extract($params, EXTR_OVERWRITE);
        ob_start();
        require $file;
        return ob_get_clean();
    }
}
