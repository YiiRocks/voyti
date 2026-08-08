<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\PasswordReset;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use YiiRocks\Voyti\Controller\PasswordReset\PasswordResetController;
use YiiRocks\Voyti\Helper\FlashType;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\ValidatorMockTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Validator\ValidatorInterface;

#[AllowMockObjectsWithoutExpectations]
final class PasswordResetControllerTest extends DatabaseTestCase
{
    use TestContainerTrait;
    use UserFactoryTrait;
    use ValidatorMockTrait;

    private FlashInterface&MockObject $flash;
    private ValidatorInterface&MockObject $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->flash = $this->createMock(FlashInterface::class);
        $this->validator = $this->mockValidValidator();
    }

    public function testRequestGetShowsForm(): void
    {
        $html = (string) $this->createController()->request(new ServerRequest('GET', '/'))->getBody();

        self::assertStringContainsString('Recover password', $html);
    }

    public function testRequestPostSuccessful(): void
    {
        $user = $this->createUser(username: 'recoveryuser', email: 'test@example.com');

        // The service's outcome message is surfaced as a success flash.
        $this->flash->expects($this->once())->method('set')->with(FlashType::SUCCESS, 'Recovery message sent');

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['recovery' => ['email' => 'test@example.com']]);

        $result = $this->createController()->request($request);

        $this->assertSame(302, $result->getStatusCode());
        $this->assertStringContainsString('session-login', $result->getHeaderLine('Location'));
        // The real RecoveryService issued a recovery token for the account.
        $this->assertNotEmpty(UserToken::findByUserId((int) $user->getId()));
    }

    public function testRequestWhenDisabledShowsError(): void
    {
        $config = VoytiConfigFactory::create(allowPasswordRecovery: false);

        $html = (string) $this->createController([VoytiConfig::class => $config])->request(new ServerRequest('GET', '/'))->getBody();

        self::assertStringContainsString('Password recovery is disabled', $html);
    }

    public function testResetGetWithValidTokenShowsForm(): void
    {
        $user = $this->createUser(username: 'recoveryuser', email: 'recoveryuser@example.com');
        $this->createRecoveryToken((int) $user->getId(), 'valid', time());

        $html = (string) $this->createController()->confirm(new ServerRequest('GET', '/'), (int) $user->getId(), 'valid')->getBody();

        self::assertStringContainsString('Reset password', $html);
    }

    public function testResetPostSuccessful(): void
    {
        $user = $this->createUser(username: 'recoveryuser', email: 'recoveryuser@example.com');
        $this->createRecoveryToken((int) $user->getId(), 'valid', time());

        $originalHash = $user->getPasswordHash();

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['recovery' => ['password' => 'newpass123', 'passwordRepeat' => 'newpass123']]);

        $result = $this->createController()->confirm($request, (int) $user->getId(), 'valid');

        $this->assertSame(302, $result->getStatusCode());
        $this->assertStringContainsString('session-login', $result->getHeaderLine('Location'));
        // The real ResetService changed the password and consumed the recovery token.
        $this->assertNotSame($originalHash, User::findById((int) $user->getId())?->getPasswordHash());
    }

    public function testResetPostWithPreviouslyUsedPasswordShowsError(): void
    {
        // The new password matches the user's current password, so the real ResetService (with
        // password-age tracking enabled) rejects it as recently used.
        $user = $this->createUser(
            username: 'recoveryuser',
            email: 'recoveryuser@example.com',
            passwordHash: TestPasswordHasherFactory::create()->hash('newpass123'),
        );
        $this->createRecoveryToken((int) $user->getId(), 'valid', time());

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['recovery' => ['password' => 'newpass123', 'passwordRepeat' => 'newpass123']]);

        $html = (string) $this->createController([VoytiConfig::class => VoytiConfigFactory::create(maxPasswordAge: 90)])
            ->confirm($request, (int) $user->getId(), 'valid')
            ->getBody();

        self::assertStringContainsString('This password has been used recently. Please choose a different one.', $html);
    }

    public function testResetWithDisabledConfigShowsMessage(): void
    {
        $config = VoytiConfigFactory::create(allowPasswordRecovery: false, allowAdminPasswordRecovery: false);

        $html = (string) $this->createController([VoytiConfig::class => $config])->confirm(new ServerRequest('GET', '/'), 1, 'code123')->getBody();

        self::assertStringContainsString('Password reset is disabled', $html);
    }

    public function testResetWithExpiredTokenShowsMessage(): void
    {
        $user = $this->createUser(username: 'recoveryuser', email: 'recoveryuser@example.com');
        $this->createRecoveryToken((int) $user->getId(), 'expired', time() - 1_000_000);

        $html = (string) $this->createController()->confirm(new ServerRequest('GET', '/'), (int) $user->getId(), 'expired')->getBody();

        self::assertStringContainsString('Recovery link is invalid or expired', $html);
    }

    private function baseOverrides(): array
    {
        return [
            FlashInterface::class => $this->flash,
        ];
    }

    private function createController(array $overrides = []): PasswordResetController
    {
        return $this->getTestContainer([
            ...$this->baseOverrides(),
            ValidatorInterface::class => $this->validator,
            ...$overrides,
        ])->get(PasswordResetController::class);
    }

    /**
     * Uses the real ValidatorInterface instead of the fast valid-by-default mock, for tests that assert on real
     * rule-generated validation messages.
     */
    private function createControllerWithRealValidation(): PasswordResetController
    {
        return $this->getTestContainer($this->baseOverrides())->get(PasswordResetController::class);
    }

    private function createRecoveryToken(int $userId, string $code, int $createdAt): UserToken
    {
        $userToken = new UserToken();
        $userToken->setUserId($userId);
        $userToken->setType(UserToken::TYPE_RECOVERY);
        $userToken->setCode(hash('sha256', $code));
        $userToken->setCreatedAt($createdAt);
        $userToken->save();

        return $userToken;
    }
}
