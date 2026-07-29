<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use ReflectionProperty;
use RuntimeException;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\MailCapture;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Mailer\MailerInterface;
use Yiisoft\View\View;

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
            new View(),
            $this->createTranslator(),
            $this->url,
            'Voyti',
        );
    }

    public function testFallbackToDefaultMailPathWhenTemplateMissingFromMailPath(): void
    {
        $emptyMailPath = sys_get_temp_dir() . '/voyti-mail-test-' . uniqid('', true);
        @mkdir($emptyMailPath, 0777, true);

        try {
            $service = new MailService(
                $this->mailer,
                $emptyMailPath,
                new View(),
                $this->createTranslator(),
                $this->url,
            );

            $result = $service->send('test@example.com', 'Test', 'welcome', ['username' => 'testuser', 'translator' => $this->createTranslator()]);
            self::assertTrue($result);
            $message = $this->mailer->getLastMessage();
            self::assertNotNull($message);
            self::assertNotNull($message->getHtmlBody());
            self::assertNotNull($message->getTextBody());
        } finally {
            @rmdir($emptyMailPath);
        }
    }

    public function testMailSubjectContainsAppName(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');

        $result = $this->service->sendWelcome($user);
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
        $ref = new ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 42);

        $result = $this->service->sendConfirmation($user, 'abcdef123');
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

        $result = $this->service->sendConfirmation($user, 'abcdef123');
        self::assertFalse($result);
    }

    public function testSendReconfirmation(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');

        $result = $this->service->sendReconfirmation($user, 'code123');
        self::assertTrue($result);
        $message = $this->mailer->getLastMessage();
        self::assertNotNull($message);
        $body = (string) $message->getHtmlBody() . (string) $message->getTextBody();
        self::assertStringContainsString('code123', $body);
    }

    public function testSendRecovery(): void
    {
        $result = $this->service->sendRecovery('testuser', 'test@example.com', 1, 'recoverycode');
        self::assertTrue($result);
        $message = $this->mailer->getLastMessage();
        self::assertNotNull($message);
        $body = (string) $message->getHtmlBody() . (string) $message->getTextBody();
        self::assertStringContainsString('id=1', $body);
        self::assertStringContainsString('code=recoverycode', $body);
    }

    public function testSendReturnsFalseWhenMailerThrows(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willThrowException(new RuntimeException('SMTP failure'));

        $service = new MailService(
            $mailer,
            __DIR__ . '/../../resources/mail',
            new View(),
            $this->createTranslator(),
            $this->url,
            'Voyti',
        );

        $result = $service->send('test@example.com', 'Test', 'welcome', ['username' => 'testuser', 'translator' => $this->createTranslator()]);

        self::assertFalse($result);
    }

    public function testSendTwoFactorCode(): void
    {
        $result = $this->service->sendTwoFactorCode('test@example.com', '123456');
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
