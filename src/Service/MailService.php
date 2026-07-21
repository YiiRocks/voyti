<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service;

use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserToken;
use Yiisoft\Mailer\MailerInterface;
use Yiisoft\Mailer\Message;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Sends the module's transactional emails (confirmation, recovery, welcome, two-factor code, etc.)
 * by rendering HTML/text view pairs from `mailPath` and dispatching them via {@see MailerInterface}.
 */
final readonly class MailService
{
    private TranslatorInterface $translator;

    public function __construct(
        private MailerInterface $mailer,
        private string $mailPath,
        TranslatorInterface $translator,
        private UrlGeneratorInterface $url,
        private string $appName = 'Voyti',
    ) {
        $this->translator = $translator->withDefaultCategory('voyti');
    }

    /**
     * @return true
     */
    public function send(string $to, string $subject, string $view, array $params = []): bool
    {
        $message = new Message(
            to: $to,
            subject: $subject,
        );

        $htmlBody = $this->renderView("html/{$view}.php", $params);
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
            $adminEmail,
            $this->getMailSubject('admin_notification_subject'),
            'welcome',
            [
                'username' => $user->getUsername(),
                'translator' => $this->translator,
            ],
        );
    }

    public function sendConfirmation(User $user, UserToken $userToken): bool
    {
        $subject = $this->getMailSubject('confirmation_subject');
        $userId = $user->getId();
        if ($userId === null) {
            return false;
        }
        return $this->send(
            $user->getEmail(),
            $subject,
            'confirmation',
            [
                'username' => $user->getUsername(),
                'confirmationUrl' => $this->url->generateAbsolute(
                    'voyti/registration-confirm',
                    ['id' => $userId, 'code' => $userToken->getCode()],
                ),
                'translator' => $this->translator,
            ],
        );
    }

    public function sendReconfirmation(User $user, UserToken $userToken): bool
    {
        $subject = $this->getMailSubject('reconfirmation_subject');
        return $this->send(
            $user->getEmail(),
            $subject,
            'reconfirmation',
            [
                'username' => $user->getUsername(),
                'confirmationUrl' => $this->url->generateAbsolute(
                    'voyti/user-account-confirm',
                    ['code' => $userToken->getCode()],
                ),
                'translator' => $this->translator,
            ],
        );
    }

    public function sendRecovery(string $username, string $email, UserToken $userToken): bool
    {
        $subject = $this->getMailSubject('recovery_subject');
        return $this->send(
            $email,
            $subject,
            'recovery',
            [
                'username' => $username,
                'recoveryUrl' => $this->url->generateAbsolute(
                    'voyti/password-reset-confirm',
                    ['id' => $userToken->getUserId(), 'code' => $userToken->getCode()],
                ),
                'translator' => $this->translator,
            ],
        );
    }

    public function sendTwoFactorCode(string $email, string $code): bool
    {
        $subject = $this->getMailSubject('two_factor_subject');
        return $this->send(
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
        $subject = $this->getMailSubject('welcome_subject');
        return $this->send(
            $user->getEmail(),
            $subject,
            'welcome',
            [
                'username' => $user->getUsername(),
                'translator' => $this->translator,
            ],
        );
    }

    private function getMailSubject(string $key): string
    {
        return $this->translator->translate(
            'voyti.mail.' . $key,
            ['app' => $this->appName],
        );
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
        $content = ob_get_contents();
        ob_end_clean();
        return $content !== false ? $content : '';
    }
}
