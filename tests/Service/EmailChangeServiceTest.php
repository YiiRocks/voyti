<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service;

use ReflectionProperty;
use YiiRocks\Voyti\Enum\EmailChangeConfirmation;
use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Model\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\Service\EmailChangeService;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\FailingMailer;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\MailCapture;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use Yiisoft\Mailer\MailerInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\View;

final class EmailChangeServiceTest extends DatabaseTestCase
{
    public function testInitiateBothReturnsFalseWhenNewFails(): void
    {
        $config = VoytiConfigFactory::create();
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createMailService(new FailingMailer()));

        $user = $this->createSavedUser();
        $form = new SettingsForm($config, $this->createStub(TranslatorInterface::class));
        $form->setUser($user);
        $form->email = 'new@example.com';

        self::assertFalse($service->initiate(EmailChangeConfirmation::BOTH, $form));
        // When the new-address step fails, the flow must short-circuit before issuing an old-address token.
        $oldTokens = array_filter(
            UserToken::findByUserId((int) $user->getId()),
            static fn(UserToken $t): bool => $t->getType() === UserToken::TYPE_CONFIRM_OLD_EMAIL,
        );
        self::assertEmpty($oldTokens);
    }

    public function testInitiateBothReturnsFalseWhenOldFails(): void
    {
        $config = VoytiConfigFactory::create();
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createMailService(new FailingMailer(succeedFor: 1)));

        $user = $this->createSavedUser();
        $form = new SettingsForm($config, $this->createStub(TranslatorInterface::class));
        $form->setUser($user);
        $form->email = 'new@example.com';

        self::assertFalse($service->initiate(EmailChangeConfirmation::BOTH, $form));
    }

    public function testInitiateBothSendsTwoConfirmationEmails(): void
    {
        $config = VoytiConfigFactory::create();
        $mailCapture = new MailCapture();
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createMailService($mailCapture));

        $user = $this->createSavedUser();
        $form = new SettingsForm($config, $this->createStubTranslator());
        $form->setUser($user);
        $form->email = 'new@example.com';

        self::assertTrue($service->initiate(EmailChangeConfirmation::BOTH, $form));
        self::assertCount(2, $mailCapture->getSentMessages());
        $tokens = UserToken::findByUserId((int) $user->getId());
        $types = array_map(static fn(UserToken $t): int => $t->getType(), $tokens);
        self::assertContains(UserToken::TYPE_CONFIRM_NEW_EMAIL, $types);
        self::assertContains(UserToken::TYPE_CONFIRM_OLD_EMAIL, $types);
    }

    public function testInitiateNewReturnsFalseWhenUserIsNull(): void
    {
        $config = VoytiConfigFactory::create();
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createMailService(new MailCapture()));
        $form = new SettingsForm($config, $this->createStub(TranslatorInterface::class));

        self::assertFalse($service->initiate(EmailChangeConfirmation::NEW, $form));
    }

    public function testInitiateNewSetsUnconfirmedEmailAndSavesToken(): void
    {
        $config = VoytiConfigFactory::create();
        $mailCapture = new MailCapture();
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createMailService($mailCapture));

        $user = $this->createSavedUser();
        $form = new SettingsForm($config, $this->createStubTranslator());
        $form->setUser($user);
        $form->email = 'new@example.com';

        self::assertTrue($service->initiate(EmailChangeConfirmation::NEW, $form));
        self::assertSame('new@example.com', $user->getUnconfirmedEmail());
        // The unconfirmed email is persisted, not just set in memory.
        self::assertSame('new@example.com', User::findById((int) $user->getId())?->getUnconfirmedEmail());
        self::assertCount(1, $mailCapture->getSentMessages());
        $tokens = UserToken::findByUserId((int) $user->getId());
        $newEmailTokens = array_filter($tokens, static fn(UserToken $t): bool => $t->getType() === UserToken::TYPE_CONFIRM_NEW_EMAIL);
        self::assertNotEmpty($newEmailTokens);
    }

    public function testInitiateNoneSetsEmailDirectly(): void
    {
        $config = VoytiConfigFactory::create();
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createMailService(new MailCapture()));

        $user = $this->createSavedUser();
        $form = new SettingsForm($config, $this->createStub(TranslatorInterface::class));
        $form->setUser($user);
        $form->email = 'new@example.com';

        self::assertTrue($service->initiate(EmailChangeConfirmation::NONE, $form));
        self::assertSame('new@example.com', $user->getEmail());
        // The new email is persisted directly, not just set in memory.
        self::assertSame('new@example.com', User::findById((int) $user->getId())?->getEmail());
    }

    public function testRunDefaultStrategy(): void
    {
        $config = VoytiConfigFactory::create(
            emailChangeConfirmation: EmailChangeConfirmation::NEW,
            tokenConfirmationLifespan: 999999,
        );
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createMailService(new MailCapture()));

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
        $config = VoytiConfigFactory::create(tokenConfirmationLifespan: 999999);
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createMailService(new MailCapture()));

        $other = $this->createSavedUser('otheruser');
        $ref = new ReflectionProperty(User::class, 'email');
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
        $config = VoytiConfigFactory::create(
            emailChangeConfirmation: EmailChangeConfirmation::NONE,
            tokenConfirmationLifespan: 999999,
        );
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createMailService(new MailCapture()));

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
        $config = VoytiConfigFactory::create(
            emailChangeConfirmation: EmailChangeConfirmation::BOTH,
            tokenConfirmationLifespan: 999999,
        );
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createMailService(new MailCapture()));

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
        $config = VoytiConfigFactory::create(
            emailChangeConfirmation: EmailChangeConfirmation::BOTH,
            tokenConfirmationLifespan: 999999,
        );
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createMailService(new MailCapture()));

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
        $config = VoytiConfigFactory::create(
            emailChangeConfirmation: EmailChangeConfirmation::BOTH,
            tokenConfirmationLifespan: 999999,
        );
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createMailService(new MailCapture()));

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
        $config = VoytiConfigFactory::create(
            emailChangeConfirmation: EmailChangeConfirmation::BOTH,
            tokenConfirmationLifespan: 999999,
        );
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createMailService(new MailCapture()));

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
        $config = VoytiConfigFactory::create(
            emailChangeConfirmation: EmailChangeConfirmation::BOTH,
            tokenConfirmationLifespan: 999999,
        );
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createMailService(new MailCapture()));

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

    public function testRunTokenExpiredReturnsFalse(): void
    {
        $config = VoytiConfigFactory::create();
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createMailService(new MailCapture()));

        $user = $this->createSavedUser();
        $token = $this->createSavedToken((int) $user->getId(), UserToken::TYPE_CONFIRM_NEW_EMAIL, time() - 200000);

        $result = $service->run('testcode_' . UserToken::TYPE_CONFIRM_NEW_EMAIL, $user);
        self::assertFalse($result);

        $reloaded = UserToken::findByUserIdAndCode((int) $user->getId(), 'testcode_' . UserToken::TYPE_CONFIRM_NEW_EMAIL);
        self::assertNull($reloaded);
    }

    public function testRunTokenExpiredReturnsFalseEvenWhenEmailCouldChange(): void
    {
        $config = VoytiConfigFactory::create(
            emailChangeConfirmation: EmailChangeConfirmation::NEW,
            tokenConfirmationLifespan: 100,
        );
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createMailService(new MailCapture()));

        $user = $this->createSavedUser();
        $user->setUnconfirmedEmail('new@example.com');
        $user->save();
        $this->createSavedToken((int) $user->getId(), UserToken::TYPE_CONFIRM_NEW_EMAIL, time() - 200000);

        $result = $service->run('testcode_' . UserToken::TYPE_CONFIRM_NEW_EMAIL, $user);
        self::assertFalse($result);
        self::assertSame('old@example.com', $user->getEmail());
    }

    public function testRunUnconfirmedEmailNullReturnsFalse(): void
    {
        $config = VoytiConfigFactory::create(tokenConfirmationLifespan: 999999);
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createMailService(new MailCapture()));

        $user = $this->createSavedUser();
        $this->createSavedToken((int) $user->getId(), UserToken::TYPE_CONFIRM_NEW_EMAIL);

        $result = $service->run('testcode_' . UserToken::TYPE_CONFIRM_NEW_EMAIL, $user);
        self::assertFalse($result);
    }

    private function createMailService(MailerInterface $mailer): MailService
    {
        return new MailService(
            $mailer,
            __DIR__ . '/../../resources/mail',
            new View(),
            $this->createStubTranslator(),
            new FakeUrlGenerator(),
            'App',
        );
    }

    private function createSavedToken(int $userId, int $type, int $createdAt = 0): UserToken
    {
        $token = new UserToken();
        $token->setUserId($userId);
        $token->setCode(hash('sha256', 'testcode_' . $type));
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

    private function createStubTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('translate')->willReturnCallback(static fn(string $id): string => $id);
        return $translator;
    }
}
