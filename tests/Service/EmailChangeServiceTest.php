<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\Service\EmailChangeService;
use YiiRocks\Voyti\Strategy\MailChangeStrategyInterface;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;

final class EmailChangeServiceTest extends TestCase
{
    use DatabaseSetupTrait;

    private UserRepository $userRepository;
    private UserTokenRepository $userTokenRepository;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->userRepository = new UserRepository();
        $this->userTokenRepository = new UserTokenRepository();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testRunDefaultStrategy(): void
    {
        $config = new ModuleConfig(
            emailChangeStrategy: MailChangeStrategyInterface::TYPE_DEFAULT,
            tokenConfirmationLifespan: 999999,
        );
        $service = new EmailChangeService($config, $this->userTokenRepository, $this->userRepository);

        $user = $this->createSavedUser();
        $user->setUnconfirmedEmail('new@example.com');
        $user->setUpdatedAt(1);
        $user->save();
        $this->createSavedToken((int) $user->getId(), UserToken::TYPE_CONFIRM_NEW_EMAIL);

        $result = $service->run('testcode_' . UserToken::TYPE_CONFIRM_NEW_EMAIL, $user);
        self::assertTrue($result);
        self::assertSame('new@example.com', $user->getEmail());
        self::assertNull($user->getUnconfirmedEmail());
        self::assertSame(0, $user->getFlags());
        self::assertGreaterThan(1, $user->getUpdatedAt());

        $reloaded = $this->userRepository->findByEmail('new@example.com');
        self::assertNotNull($reloaded);
        self::assertNull($this->userTokenRepository->findByUserIdAndCode((int) $user->getId(), 'testcode_' . UserToken::TYPE_CONFIRM_NEW_EMAIL));
    }

    public function testRunExistingEmailConflictReturnsFalse(): void
    {
        $config = new ModuleConfig(tokenConfirmationLifespan: 999999);
        $service = new EmailChangeService($config, $this->userTokenRepository, $this->userRepository);

        $other = $this->createSavedUser();
        $ref = new \ReflectionProperty(User::class, 'email');
        $ref->setValue($other, 'existing@example.com');
        $other->save();

        $user = $this->createSavedUser();
        $user->setUnconfirmedEmail('existing@example.com');
        $user->save();
        $this->createSavedToken((int) $user->getId(), UserToken::TYPE_CONFIRM_NEW_EMAIL);

        $result = $service->run('testcode_' . UserToken::TYPE_CONFIRM_NEW_EMAIL, $user);
        self::assertFalse($result);
    }

    public function testRunInsecureStrategyOnlyNewFlagDoesNotChangeEmail(): void
    {
        $config = new ModuleConfig(
            emailChangeStrategy: MailChangeStrategyInterface::TYPE_INSECURE,
            tokenConfirmationLifespan: 999999,
        );
        $service = new EmailChangeService($config, $this->userTokenRepository, $this->userRepository);

        $user = $this->createSavedUser();
        $user->setUnconfirmedEmail('new@example.com');
        $user->setFlags(User::NEW_EMAIL_CONFIRMED);
        $user->save();
        $this->createSavedToken((int) $user->getId(), UserToken::TYPE_CONFIRM_NEW_EMAIL);

        $result = $service->run('testcode_' . UserToken::TYPE_CONFIRM_NEW_EMAIL, $user);
        self::assertTrue($result);
        self::assertSame('old@example.com', $user->getEmail());
        self::assertSame('new@example.com', $user->getUnconfirmedEmail());
    }

    public function testRunSecureOldEmailTokenOnlyOldFlagDoesNotChangeEmail(): void
    {
        $config = new ModuleConfig(
            emailChangeStrategy: MailChangeStrategyInterface::TYPE_SECURE,
            tokenConfirmationLifespan: 999999,
        );
        $service = new EmailChangeService($config, $this->userTokenRepository, $this->userRepository);

        $user = $this->createSavedUser();
        $user->setUnconfirmedEmail('new@example.com');
        $user->setFlags(User::OLD_EMAIL_CONFIRMED);
        $user->save();
        $this->createSavedToken((int) $user->getId(), UserToken::TYPE_CONFIRM_OLD_EMAIL);

        $result = $service->run('testcode_' . UserToken::TYPE_CONFIRM_OLD_EMAIL, $user);
        self::assertTrue($result);
        self::assertSame('old@example.com', $user->getEmail());
        self::assertSame('new@example.com', $user->getUnconfirmedEmail());
    }

    public function testRunSecureOldEmailTokenWithoutInitialFlagSetsOldFlag(): void
    {
        $config = new ModuleConfig(
            emailChangeStrategy: MailChangeStrategyInterface::TYPE_SECURE,
            tokenConfirmationLifespan: 999999,
        );
        $service = new EmailChangeService($config, $this->userTokenRepository, $this->userRepository);

        $user = $this->createSavedUser();
        $user->setUnconfirmedEmail('new@example.com');
        $user->save();
        $this->createSavedToken((int) $user->getId(), UserToken::TYPE_CONFIRM_OLD_EMAIL);

        $result = $service->run('testcode_' . UserToken::TYPE_CONFIRM_OLD_EMAIL, $user);
        self::assertTrue($result);
        self::assertSame(User::OLD_EMAIL_CONFIRMED, $user->getFlags());
    }

    public function testRunSecureStrategyBothFlagsAlreadySet(): void
    {
        $config = new ModuleConfig(
            emailChangeStrategy: MailChangeStrategyInterface::TYPE_SECURE,
            tokenConfirmationLifespan: 999999,
        );
        $service = new EmailChangeService($config, $this->userTokenRepository, $this->userRepository);

        $user = $this->createSavedUser();
        $user->setUnconfirmedEmail('new@example.com');
        $user->setFlags(User::NEW_EMAIL_CONFIRMED | User::OLD_EMAIL_CONFIRMED);
        $user->save();
        $this->createSavedToken((int) $user->getId(), UserToken::TYPE_CONFIRM_OLD_EMAIL);

        $result = $service->run('testcode_' . UserToken::TYPE_CONFIRM_OLD_EMAIL, $user);
        self::assertTrue($result);
        self::assertSame('new@example.com', $user->getEmail());
        self::assertNull($user->getUnconfirmedEmail());
        self::assertSame(0, $user->getFlags());
    }

    public function testRunSecureStrategyNewEmailToken(): void
    {
        $config = new ModuleConfig(
            emailChangeStrategy: MailChangeStrategyInterface::TYPE_SECURE,
            tokenConfirmationLifespan: 999999,
        );
        $service = new EmailChangeService($config, $this->userTokenRepository, $this->userRepository);

        $user = $this->createSavedUser();
        $user->setUnconfirmedEmail('new@example.com');
        $user->save();
        $this->createSavedToken((int) $user->getId(), UserToken::TYPE_CONFIRM_NEW_EMAIL);

        $result = $service->run('testcode_' . UserToken::TYPE_CONFIRM_NEW_EMAIL, $user);
        self::assertTrue($result);
        self::assertSame(User::NEW_EMAIL_CONFIRMED, $user->getFlags());
        self::assertSame('old@example.com', $user->getEmail());

        $reloaded = $this->userRepository->findByEmail('old@example.com');
        self::assertNotNull($reloaded);
        self::assertSame(User::NEW_EMAIL_CONFIRMED, $reloaded->getFlags());
    }

    public function testRunSecureStrategyNewEmailTokenWithBothFlagsDoesNotChangeEmail(): void
    {
        $config = new ModuleConfig(
            emailChangeStrategy: MailChangeStrategyInterface::TYPE_SECURE,
            tokenConfirmationLifespan: 999999,
        );
        $service = new EmailChangeService($config, $this->userTokenRepository, $this->userRepository);

        $user = $this->createSavedUser();
        $user->setUnconfirmedEmail('new@example.com');
        $user->setFlags(User::NEW_EMAIL_CONFIRMED | User::OLD_EMAIL_CONFIRMED);
        $user->save();
        $this->createSavedToken((int) $user->getId(), UserToken::TYPE_CONFIRM_NEW_EMAIL);

        $result = $service->run('testcode_' . UserToken::TYPE_CONFIRM_NEW_EMAIL, $user);
        self::assertTrue($result);
        self::assertSame('old@example.com', $user->getEmail());
        self::assertSame('new@example.com', $user->getUnconfirmedEmail());
        self::assertSame(User::NEW_EMAIL_CONFIRMED | User::OLD_EMAIL_CONFIRMED, $user->getFlags());
    }

    public function testRunSecureStrategyOldEmailToken(): void
    {
        $config = new ModuleConfig(
            emailChangeStrategy: MailChangeStrategyInterface::TYPE_SECURE,
            tokenConfirmationLifespan: 999999,
        );
        $service = new EmailChangeService($config, $this->userTokenRepository, $this->userRepository);

        $user = $this->createSavedUser();
        $user->setUnconfirmedEmail('new@example.com');
        $user->save();
        $this->createSavedToken((int) $user->getId(), UserToken::TYPE_CONFIRM_OLD_EMAIL);

        $result = $service->run('testcode_' . UserToken::TYPE_CONFIRM_OLD_EMAIL, $user);
        self::assertTrue($result);
        self::assertSame('old@example.com', $user->getEmail());
        self::assertSame('new@example.com', $user->getUnconfirmedEmail());
        self::assertSame(User::OLD_EMAIL_CONFIRMED, $user->getFlags());
    }

    public function testRunTokenExpiredReturnsFalse(): void
    {
        $config = new ModuleConfig();
        $service = new EmailChangeService($config, $this->userTokenRepository, $this->userRepository);

        $user = $this->createSavedUser();
        $token = $this->createSavedToken((int) $user->getId(), UserToken::TYPE_CONFIRM_NEW_EMAIL, time() - 200000);

        $result = $service->run('testcode_' . UserToken::TYPE_CONFIRM_NEW_EMAIL, $user);
        self::assertFalse($result);

        $reloaded = $this->userTokenRepository->findByUserIdAndCode((int) $user->getId(), 'testcode_' . UserToken::TYPE_CONFIRM_NEW_EMAIL);
        self::assertNull($reloaded);
    }

    public function testRunTokenExpiredReturnsFalseEvenWhenEmailCouldChange(): void
    {
        $config = new ModuleConfig(
            emailChangeStrategy: MailChangeStrategyInterface::TYPE_DEFAULT,
            tokenConfirmationLifespan: 100,
        );
        $service = new EmailChangeService($config, $this->userTokenRepository, $this->userRepository);

        $user = $this->createSavedUser();
        $user->setUnconfirmedEmail('new@example.com');
        $user->save();
        $this->createSavedToken((int) $user->getId(), UserToken::TYPE_CONFIRM_NEW_EMAIL, time() - 200000);

        $result = $service->run('testcode_' . UserToken::TYPE_CONFIRM_NEW_EMAIL, $user);
        self::assertFalse($result);
        self::assertSame('old@example.com', $user->getEmail());
    }

    public function testRunTokenNotFoundReturnsFalse(): void
    {
        $config = new ModuleConfig();
        $service = new EmailChangeService($config, $this->userTokenRepository, $this->userRepository);

        $user = $this->createSavedUser();
        $result = $service->run('nonexistent', $user);
        self::assertFalse($result);
    }

    public function testRunTokenWrongTypeReturnsFalse(): void
    {
        $config = new ModuleConfig();
        $service = new EmailChangeService($config, $this->userTokenRepository, $this->userRepository);

        $user = $this->createSavedUser();
        $this->createSavedToken((int) $user->getId(), 99);

        $result = $service->run('testcode_99', $user);
        self::assertFalse($result);
    }

    public function testRunUnconfirmedEmailNullReturnsFalse(): void
    {
        $config = new ModuleConfig(tokenConfirmationLifespan: 999999);
        $service = new EmailChangeService($config, $this->userTokenRepository, $this->userRepository);

        $user = $this->createSavedUser();
        $this->createSavedToken((int) $user->getId(), UserToken::TYPE_CONFIRM_NEW_EMAIL);

        $result = $service->run('testcode_' . UserToken::TYPE_CONFIRM_NEW_EMAIL, $user);
        self::assertFalse($result);
    }

    public function testRunWithNullUserIdUsesZeroNotMinusOne(): void
    {
        $config = new ModuleConfig(
            emailChangeStrategy: MailChangeStrategyInterface::TYPE_DEFAULT,
            tokenConfirmationLifespan: 999999,
        );
        $service = new EmailChangeService($config, $this->userTokenRepository, $this->userRepository);

        $user = new User();
        $user->setUsername('nulluser');
        $user->setEmail('nullold@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->setUnconfirmedEmail('nullnew@example.com');

        $token = new UserToken();
        $token->setUserId(0);
        $token->setCode('zerocode');
        $token->setType(UserToken::TYPE_CONFIRM_NEW_EMAIL);
        $token->setCreatedAt(time());
        $token->save();

        $result = $service->run('zerocode', $user);
        self::assertTrue($result);
        self::assertSame('nullnew@example.com', $user->getEmail());
    }

    public function testRunWithNullUserIdUsesZeroNotOne(): void
    {
        $config = new ModuleConfig(
            emailChangeStrategy: MailChangeStrategyInterface::TYPE_DEFAULT,
            tokenConfirmationLifespan: 999999,
        );
        $service = new EmailChangeService($config, $this->userTokenRepository, $this->userRepository);

        $user = new User();
        $user->setUsername('nulluser2');
        $user->setEmail('nullold2@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->setUnconfirmedEmail('nullnew2@example.com');

        $token = new UserToken();
        $token->setUserId(1);
        $token->setCode('onecode');
        $token->setType(UserToken::TYPE_CONFIRM_NEW_EMAIL);
        $token->setCreatedAt(time());
        $token->save();

        $result = $service->run('onecode', $user);
        self::assertFalse($result);
    }

    private function createSavedToken(int $userId, int $type, int $createdAt = 0): UserToken
    {
        $token = new UserToken();
        $token->setUserId($userId);
        $token->setCode('testcode_' . $type);
        $token->setType($type);
        $token->setCreatedAt($createdAt !== 0 ? $createdAt : time());
        $token->save();
        return $token;
    }

    private function createSavedUser(): User
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('old@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();
        return $user;
    }
}
