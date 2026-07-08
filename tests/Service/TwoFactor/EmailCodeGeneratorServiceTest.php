<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\TwoFactor;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Service\TwoFactor\EmailCodeGeneratorService;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class EmailCodeGeneratorServiceTest extends TestCase
{
    public function testRunGeneratesCodeAndSendsEmail(): void
    {
        $mailService = $this->createMock(MailService::class);
        $mailService->expects($this->once())
            ->method('sendTwoFactorCode')
            ->with('user@example.com', $this->matchesRegularExpression('/^\d{6}$/'));

        $service = new EmailCodeGeneratorService($mailService);

        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn('user@example.com');
        $user->expects($this->once())->method('setAuthTfKey');
        $user->expects($this->once())->method('save');

        $code = $service->run($user);

        self::assertMatchesRegularExpression('/^\d{6}$/', $code);
    }

    public function testRunReturnsSixDigitCode(): void
    {
        $mailService = $this->createMock(MailService::class);
        $mailService->method('sendTwoFactorCode')->willReturn(true);

        $service = new EmailCodeGeneratorService($mailService);

        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn('user@example.com');

        $code = $service->run($user);

        self::assertIsString($code);
        self::assertSame(6, strlen($code));
    }
}
