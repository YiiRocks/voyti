<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Enum\EmailChangeConfirmation;
use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Model\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\EmailChangeService;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\MailCapture;
use Yiisoft\Translator\TranslatorInterface;

final class EmailChangeServiceTest extends TestCase
{
    use DatabaseSetupTrait;

    protected function setUp(): void
    {
        $this->setUpDatabase();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testInitiateBothReturnsFalseWhenNewFails(): void
    {
        $config = new ModuleConfig();
        $mailService = $this->createStub(MailService::class);
        $mailService->method('sendReconfirmation')->willReturn(false);
        $service = new EmailChangeService($config, new UserTokenFactory(), $mailService);

        $user = $this->createSavedUser();
        $form = new SettingsForm($config, $this->createStub(\Yiisoft\Translator\TranslatorInterface::class));
        $form->setUser($user);
        $form->email = 'new@example.com';

        self::assertFalse($service->initiate(EmailChangeConfirmation::BOTH, $form));
    }

    public function testInitiateBothSendsTwoConfirmationEmails(): void
    {
        $config = new ModuleConfig();
        $mailCapture = new MailCapture();
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createMailService($mailCapture));

        $user = $this->createSavedUser();
        $form = new SettingsForm($config, $this->createTranslator());
        $form->setUser($user);
        $form->email = 'new@example.com';

        self::assertTrue($service->initiate(EmailChangeConfirmation::BOTH, $form));
        self::assertCount(2, $mailCapture->getSentMessages());
        $tokens = UserToken::findByUserId((int) $user->getId());
        $types = array_map(static fn (UserToken $t): int => $t->getType(), $tokens);
        self::assertContains(UserToken::TYPE_CONFIRM_NEW_EMAIL, $types);
        self::assertContains(UserToken::TYPE_CONFIRM_OLD_EMAIL, $types);
    }

    public function testInitiateNewPersistsTokenWithZeroUserIdWhenUserUnsaved(): void
    {
        $config = new ModuleConfig();
        $mailCapture = new MailCapture();
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createMailService($mailCapture));

        $user = new User();
        $user->setUsername('unsaved');
        $user->setEmail('old@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        self::assertNull($user->getId());

        $form = new SettingsForm($config, $this->createTranslator());
        $form->setUser($user);
        $form->email = 'new@example.com';

        self::assertTrue($service->initiate(EmailChangeConfirmation::NEW, $form));

        $tokens = UserToken::findByUserId(0);
        $newEmailTokens = array_filter($tokens, static fn (UserToken $t): bool => $t->getType() === UserToken::TYPE_CONFIRM_NEW_EMAIL);
        self::assertNotEmpty($newEmailTokens);
    }

    public function testInitiateNewReturnsFalseWhenUserIsNull(): void
    {
        $config = new ModuleConfig();
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createStub(MailService::class));
        $form = new SettingsForm($config, $this->createStub(\Yiisoft\Translator\TranslatorInterface::class));

        self::assertFalse($service->initiate(EmailChangeConfirmation::NEW, $form));
    }

    public function testInitiateNewSetsUnconfirmedEmailAndSavesToken(): void
    {
        $config = new ModuleConfig();
        $mailCapture = new MailCapture();
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createMailService($mailCapture));

        $user = $this->createSavedUser();
        $form = new SettingsForm($config, $this->createTranslator());
        $form->setUser($user);
        $form->email = 'new@example.com';

        self::assertTrue($service->initiate(EmailChangeConfirmation::NEW, $form));
        self::assertSame('new@example.com', $user->getUnconfirmedEmail());
        self::assertCount(1, $mailCapture->getSentMessages());
        $tokens = UserToken::findByUserId((int) $user->getId());
        $newEmailTokens = array_filter($tokens, static fn (UserToken $t): bool => $t->getType() === UserToken::TYPE_CONFIRM_NEW_EMAIL);
        self::assertNotEmpty($newEmailTokens);
    }

    public function testInitiateNoneReturnsFalseWhenUserIsNull(): void
    {
        $config = new ModuleConfig();
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createStub(MailService::class));
        $form = new SettingsForm($config, $this->createStub(\Yiisoft\Translator\TranslatorInterface::class));

        self::assertFalse($service->initiate(EmailChangeConfirmation::NONE, $form));
    }

    public function testInitiateNoneSetsEmailDirectly(): void
    {
        $config = new ModuleConfig();
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createStub(MailService::class));

        $user = $this->createSavedUser();
        $form = new SettingsForm($config, $this->createStub(\Yiisoft\Translator\TranslatorInterface::class));
        $form->setUser($user);
        $form->email = 'new@example.com';

        self::assertTrue($service->initiate(EmailChangeConfirmation::NONE, $form));
        self::assertSame('new@example.com', $user->getEmail());
    }

    public function testRunDefaultStrategy(): void
    {
        $config = new ModuleConfig(
            emailChangeConfirmation: EmailChangeConfirmation::NEW,
            tokenConfirmationLifespan: 999999,
        );
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createStub(MailService::class));

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

        $reloaded = User::findByEmail('new@example.com');
        self::assertNotNull($reloaded);
        self::assertNull(UserToken::findByUserIdAndCode((int) $user->getId(), 'testcode_' . UserToken::TYPE_CONFIRM_NEW_EMAIL));
    }

    public function testRunExistingEmailConflictReturnsFalse(): void
    {
        $config = new ModuleConfig(tokenConfirmationLifespan: 999999);
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createStub(MailService::class));

        $other = $this->createSavedUser('otheruser');
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
            emailChangeConfirmation: EmailChangeConfirmation::NONE,
            tokenConfirmationLifespan: 999999,
        );
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createStub(MailService::class));

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
            emailChangeConfirmation: EmailChangeConfirmation::BOTH,
            tokenConfirmationLifespan: 999999,
        );
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createStub(MailService::class));

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
            emailChangeConfirmation: EmailChangeConfirmation::BOTH,
            tokenConfirmationLifespan: 999999,
        );
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createStub(MailService::class));

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
            emailChangeConfirmation: EmailChangeConfirmation::BOTH,
            tokenConfirmationLifespan: 999999,
        );
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createStub(MailService::class));

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
            emailChangeConfirmation: EmailChangeConfirmation::BOTH,
            tokenConfirmationLifespan: 999999,
        );
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createStub(MailService::class));

        $user = $this->createSavedUser();
        $user->setUnconfirmedEmail('new@example.com');
        $user->save();
        $this->createSavedToken((int) $user->getId(), UserToken::TYPE_CONFIRM_NEW_EMAIL);

        $result = $service->run('testcode_' . UserToken::TYPE_CONFIRM_NEW_EMAIL, $user);
        self::assertTrue($result);
        self::assertSame(User::NEW_EMAIL_CONFIRMED, $user->getFlags());
        self::assertSame('old@example.com', $user->getEmail());

        $reloaded = User::findByEmail('old@example.com');
        self::assertNotNull($reloaded);
        self::assertSame(User::NEW_EMAIL_CONFIRMED, $reloaded->getFlags());
    }

    public function testRunSecureStrategyNewEmailTokenWithBothFlagsDoesNotChangeEmail(): void
    {
        $config = new ModuleConfig(
            emailChangeConfirmation: EmailChangeConfirmation::BOTH,
            tokenConfirmationLifespan: 999999,
        );
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createStub(MailService::class));

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
            emailChangeConfirmation: EmailChangeConfirmation::BOTH,
            tokenConfirmationLifespan: 999999,
        );
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createStub(MailService::class));

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
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createStub(MailService::class));

        $user = $this->createSavedUser();
        $token = $this->createSavedToken((int) $user->getId(), UserToken::TYPE_CONFIRM_NEW_EMAIL, time() - 200000);

        $result = $service->run('testcode_' . UserToken::TYPE_CONFIRM_NEW_EMAIL, $user);
        self::assertFalse($result);

        $reloaded = UserToken::findByUserIdAndCode((int) $user->getId(), 'testcode_' . UserToken::TYPE_CONFIRM_NEW_EMAIL);
        self::assertNull($reloaded);
    }

    public function testRunTokenExpiredReturnsFalseEvenWhenEmailCouldChange(): void
    {
        $config = new ModuleConfig(
            emailChangeConfirmation: EmailChangeConfirmation::NEW,
            tokenConfirmationLifespan: 100,
        );
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createStub(MailService::class));

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
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createStub(MailService::class));

        $user = $this->createSavedUser();
        $result = $service->run('nonexistent', $user);
        self::assertFalse($result);
    }

    public function testRunTokenWrongTypeReturnsFalse(): void
    {
        $config = new ModuleConfig();
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createStub(MailService::class));

        $user = $this->createSavedUser();
        $this->createSavedToken((int) $user->getId(), 99);

        $result = $service->run('testcode_99', $user);
        self::assertFalse($result);
    }

    public function testRunUnconfirmedEmailNullReturnsFalse(): void
    {
        $config = new ModuleConfig(tokenConfirmationLifespan: 999999);
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createStub(MailService::class));

        $user = $this->createSavedUser();
        $this->createSavedToken((int) $user->getId(), UserToken::TYPE_CONFIRM_NEW_EMAIL);

        $result = $service->run('testcode_' . UserToken::TYPE_CONFIRM_NEW_EMAIL, $user);
        self::assertFalse($result);
    }

    public function testRunWithNullUserIdUsesZeroNotMinusOne(): void
    {
        $config = new ModuleConfig(
            emailChangeConfirmation: EmailChangeConfirmation::NEW,
            tokenConfirmationLifespan: 999999,
        );
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createStub(MailService::class));

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
            emailChangeConfirmation: EmailChangeConfirmation::NEW,
            tokenConfirmationLifespan: 999999,
        );
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createStub(MailService::class));

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

    private function createMailService(MailCapture $mailCapture): MailService
    {
        return new MailService(
            $mailCapture,
            __DIR__ . '/../../resources/mail',
            $this->createTranslator(),
            new FakeUrlGenerator(),
            'App',
        );
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

    private function createSavedUser(string $username = 'testuser'): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail('old@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();
        return $user;
    }

    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('translate')->willReturnCallback(static fn (string $id): string => $id);
        return $translator;
    }
}
