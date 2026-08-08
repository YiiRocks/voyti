<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\TwoFactor;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\TwoFactor\EmailCodeGeneratorService;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\MailCapture;
use YiiRocks\Voyti\tests\Support\MailServiceFactoryTrait;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;

#[AllowMockObjectsWithoutExpectations]
final class EmailCodeGeneratorServiceTest extends DatabaseTestCase
{
    use MailServiceFactoryTrait;
    use UserFactoryTrait;

    public function testRunGeneratesCodeAndSendsEmail(): void
    {
        $mailCapture = new MailCapture();
        $service = new EmailCodeGeneratorService($this->createMailService($mailCapture));

        $user = $this->createUser(email: 'user@example.com');

        $code = $service->run($user);

        self::assertMatchesRegularExpression('/^\d{6}$/', $code);
        self::assertNotNull($user->getAuthTfKey());
        self::assertSame($user->getAuthTfKey(), User::findById((int) $user->getId())?->getAuthTfKey());

        $message = $mailCapture->getLastMessage();
        self::assertNotNull($message);
        self::assertSame('user@example.com', $message->getTo());
        self::assertStringContainsString($code, (string) $message->getHtmlBody());
    }
}
