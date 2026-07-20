<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\MailCapture;
use YiiRocks\Voyti\tests\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class MailServiceTest extends TestCase
{
    private MailCapture $mailer;
    private MailService $service;
    private FakeUrlGenerator $url;

    protected function setUp(): void
    {
        $this->mailer = new MailCapture();
        $this->url = new FakeUrlGenerator();

        $this->service = new MailService(
            $this->mailer,
            __DIR__ . '/../../resources/mail',
            $this->createTranslator(),
            $this->url,
            'Voyti',
        );
    }

    public function testMailSubjectContainsAppName(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');

        $result = $this->service->sendWelcome($user, 'password123');
        self::assertTrue($result);
        $message = $this->mailer->getLastMessage();
        self::assertNotNull($message);
        self::assertSame('Welcome to Voyti', (string) $message->getSubject());
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
        $result = $this->service->send('test@example.com', 'Test', 'welcome', ['username' => 'testuser', 'translator' => $this->createTranslator()]);
        self::assertTrue($result);
        $message = $this->mailer->getLastMessage();
        self::assertNotNull($message);
        self::assertNotNull($message->getHtmlBody());
        self::assertNotNull($message->getTextBody());
    }
}
