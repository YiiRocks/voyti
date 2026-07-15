<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\MailCapture;
use Yiisoft\Translator\TranslatorInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class MailServiceTest extends TestCase
{
    private MailCapture $mailer;
    private MailService $service;
    private FakeUrlGenerator $url;

    protected function setUp(): void
    {
        $this->mailer = new MailCapture();
        $this->url = new FakeUrlGenerator();
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('translate')->willReturnCallback(fn (string $key) => match (true) {
            str_contains($key, 'subject') => 'Subject ' . $key,
            default => $key,
        });

        $this->service = new MailService(
            $this->mailer,
            __DIR__ . '/../../resources/mail',
            $translator,
            $this->url,
            'Voyti',
        );
    }

    public function testMailSubjectContainsAppName(): void
    {
        $captured = [];
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->method('translate')
            ->willReturnCallback(static function (string $key, array $params = [], string $category = '') use (&$captured): string {
                $captured[] = ['key' => $key, 'params' => $params, 'category' => $category];

                return str_contains($key, 'subject') ? 'Subject ' . $key : $key;
            });

        $service = new MailService(
            $this->mailer,
            __DIR__ . '/../../resources/mail',
            $translator,
            $this->url,
            'Voyti',
        );

        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');

        $result = $service->sendWelcome($user, 'password123');
        self::assertTrue($result);
        $message = $this->mailer->getLastMessage();
        self::assertNotNull($message);
        self::assertStringContainsString('voyti.mail.welcome_subject', (string) $message->getSubject());

        $subjectCalls = array_filter($captured, static fn (array $c): bool => str_contains($c['key'], 'subject'));
        self::assertNotEmpty($subjectCalls);
        foreach ($subjectCalls as $call) {
            self::assertSame('Voyti', $call['params']['app']);
            self::assertSame('voyti', $call['category']);
        }
    }

    public function testSendAdminNotification(): void
    {
        $user = new User();
        $user->setUsername('testuser');

        $result = $this->service->sendAdminNotification('admin@example.com', $user);
        self::assertTrue($result);
    }

    public function testSendConfirmationSuccess(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 42);

        $userToken = new UserToken();
        $userToken->setCode('abcdef123');

        $result = $this->service->sendConfirmation($user, $userToken);
        self::assertTrue($result);
        $message = $this->mailer->getLastMessage();
        self::assertNotNull($message);
        $body = (string) $message->getHtmlBody() . (string) $message->getTextBody();
        self::assertStringContainsString('id=42', $body);
        self::assertStringContainsString('abcdef123', $body);
    }

    public function testSendConfirmationWithNullUserIdReturnsFalse(): void
    {
        $user = new User();
        $userToken = new UserToken();

        $result = $this->service->sendConfirmation($user, $userToken);
        self::assertFalse($result);
    }

    public function testSendReconfirmation(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');
        $userToken = new UserToken();
        $userToken->setCode('code123');

        $result = $this->service->sendReconfirmation($user, $userToken);
        self::assertTrue($result);
        $message = $this->mailer->getLastMessage();
        self::assertNotNull($message);
        $body = (string) $message->getHtmlBody() . (string) $message->getTextBody();
        self::assertStringContainsString('code123', $body);
    }

    public function testSendRecovery(): void
    {
        $userToken = new UserToken();
        $userToken->setCode('recoverycode');
        $ref = new \ReflectionProperty(UserToken::class, 'user_id');
        $ref->setValue($userToken, 1);

        $result = $this->service->sendRecovery('testuser', 'test@example.com', $userToken);
        self::assertTrue($result);
        $message = $this->mailer->getLastMessage();
        self::assertNotNull($message);
        $body = (string) $message->getHtmlBody() . (string) $message->getTextBody();
        self::assertStringContainsString('id=1', $body);
        self::assertStringContainsString('code=recoverycode', $body);
    }

    public function testSendTwoFactorCode(): void
    {
        $result = $this->service->sendTwoFactorCode('test@example.com', '123456');
        self::assertTrue($result);
    }

    public function testSendWelcome(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');

        $result = $this->service->sendWelcome($user, 'password123');
        self::assertTrue($result);
    }

    public function testSendWithBothHtmlAndText(): void
    {
        $result = $this->service->send('test@example.com', 'Test', 'welcome', ['username' => 'testuser', 'translator' => $this->createMock(TranslatorInterface::class)]);
        self::assertTrue($result);
        $message = $this->mailer->getLastMessage();
        self::assertNotNull($message);
        self::assertNotNull($message->getHtmlBody());
        self::assertNotNull($message->getTextBody());
    }
}
