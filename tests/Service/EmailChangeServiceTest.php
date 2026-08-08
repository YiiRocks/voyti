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
    public function testInitiateErrors(): void
    {
        $config = VoytiConfigFactory::create();

        // BOTH strategy fails when sending new email fails
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createMailService(new FailingMailer()));
        $user = $this->createSavedUser(username: 'err_new');
        $user->setEmail('err_new@example.com');
        $user->save();
        $form = new SettingsForm($config, $this->createStub(TranslatorInterface::class));
        $form->setUser($user);
        $form->email = 'fail1@example.com';
        self::assertFalse($service->initiate(EmailChangeConfirmation::BOTH, $form));
        $oldTokens = array_filter(
            UserToken::findByUserId((int) $user->getId()),
            static fn(UserToken $t): bool => $t->getType() === UserToken::TYPE_CONFIRM_OLD_EMAIL,
        );
        self::assertEmpty($oldTokens);

        // BOTH strategy fails when sending old email fails
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createMailService(new FailingMailer(succeedFor: 1)));
        $user2 = $this->createSavedUser(username: 'err_old');
        $user2->setEmail('err_old@example.com');
        $user2->save();
        $form = new SettingsForm($config, $this->createStub(TranslatorInterface::class));
        $form->setUser($user2);
        $form->email = 'fail2@example.com';
        self::assertFalse($service->initiate(EmailChangeConfirmation::BOTH, $form));
    }

    public function testInitiateStrategies(): void
    {
        $config = VoytiConfigFactory::create();

        // NONE strategy: sets email directly
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createMailService(new MailCapture()));
        $user = $this->createSavedUser(username: 'init_none');
        $user->setEmail('init_none@example.com');
        $user->save();
        $form = new SettingsForm($config, $this->createStub(TranslatorInterface::class));
        $form->setUser($user);
        $form->email = 'none_new@example.com';
        self::assertTrue($service->initiate(EmailChangeConfirmation::NONE, $form));
        self::assertSame('none_new@example.com', $user->getEmail());
        self::assertSame('none_new@example.com', User::findById((int) $user->getId())?->getEmail());

        // NEW strategy: sets unconfirmed email with token
        $user2 = $this->createSavedUser(username: 'init_new');
        $user2->setEmail('init_new@example.com');
        $user2->save();
        $mailCapture = new MailCapture();
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createMailService($mailCapture));
        $form = new SettingsForm($config, $this->createStubTranslator());
        $form->setUser($user2);
        $form->email = 'new_new@example.com';
        self::assertTrue($service->initiate(EmailChangeConfirmation::NEW, $form));
        self::assertSame('new_new@example.com', $user2->getUnconfirmedEmail());
        self::assertSame('new_new@example.com', User::findById((int) $user2->getId())?->getUnconfirmedEmail());
        self::assertCount(1, $mailCapture->getSentMessages());

        // BOTH strategy: sends two emails with both token types
        $user3 = $this->createSavedUser(username: 'init_both');
        $user3->setEmail('init_both@example.com');
        $user3->save();
        $mailCapture2 = new MailCapture();
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createMailService($mailCapture2));
        $form = new SettingsForm($config, $this->createStubTranslator());
        $form->setUser($user3);
        $form->email = 'both_new@example.com';
        self::assertTrue($service->initiate(EmailChangeConfirmation::BOTH, $form));
        self::assertCount(2, $mailCapture2->getSentMessages());
        $tokens = UserToken::findByUserId((int) $user3->getId());
        $types = array_map(static fn(UserToken $t): int => $t->getType(), $tokens);
        self::assertContains(UserToken::TYPE_CONFIRM_NEW_EMAIL, $types);
        self::assertContains(UserToken::TYPE_CONFIRM_OLD_EMAIL, $types);

        // NEW strategy without user: returns false
        $formNoUser = new SettingsForm($config, $this->createStub(TranslatorInterface::class));
        self::assertFalse($service->initiate(EmailChangeConfirmation::NEW, $formNoUser));
    }

    public function testRunDefault(): void
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

    public function testRunErrors(): void
    {
        // Token expired
        $config = VoytiConfigFactory::create();
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createMailService(new MailCapture()));
        $user1 = $this->createSavedUser(username: 'err_expired');
        $user1->setEmail('err_expired@example.com');
        $user1->save();
        $this->createSavedToken((int) $user1->getId(), UserToken::TYPE_CONFIRM_NEW_EMAIL, time() - 200000);
        $result = $service->run('testcode_' . UserToken::TYPE_CONFIRM_NEW_EMAIL, $user1);
        self::assertFalse($result);
        self::assertNull(UserToken::findByUserIdAndCode((int) $user1->getId(), 'testcode_' . UserToken::TYPE_CONFIRM_NEW_EMAIL));

        // Unconfirmed email null
        $config = VoytiConfigFactory::create(tokenConfirmationLifespan: 999999);
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createMailService(new MailCapture()));
        $user2 = $this->createSavedUser(username: 'err_null');
        $user2->setEmail('err_null@example.com');
        $user2->save();
        $this->createSavedToken((int) $user2->getId(), UserToken::TYPE_CONFIRM_NEW_EMAIL);
        $result = $service->run('testcode_' . UserToken::TYPE_CONFIRM_NEW_EMAIL, $user2);
        self::assertFalse($result);

        // Email conflict with existing user
        $config = VoytiConfigFactory::create(tokenConfirmationLifespan: 999999);
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createMailService(new MailCapture()));
        $other = $this->createSavedUser(username: 'conflict_user');
        $other->setEmail('conflict_user@example.com');
        $ref = new ReflectionProperty(User::class, 'email');
        $ref->setValue($other, 'conflict@example.com');
        $other->save();
        $user3 = $this->createSavedUser(username: 'err_conflict');
        $user3->setEmail('err_conflict@example.com');
        $user3->setUnconfirmedEmail('conflict@example.com');
        $user3->save();
        $this->createSavedToken((int) $user3->getId(), UserToken::TYPE_CONFIRM_NEW_EMAIL);
        $result = $service->run('testcode_' . UserToken::TYPE_CONFIRM_NEW_EMAIL, $user3);
        self::assertFalse($result);

        // Insecure strategy (NONE) with NEW_EMAIL_CONFIRMED flag: doesn't apply
        $config = VoytiConfigFactory::create(
            emailChangeConfirmation: EmailChangeConfirmation::NONE,
            tokenConfirmationLifespan: 999999,
        );
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createMailService(new MailCapture()));
        $user4 = $this->createSavedUser(username: 'err_insecure');
        $user4->setEmail('err_insecure@example.com');
        $user4->setUnconfirmedEmail('err_insecure_new@example.com');
        $user4->setFlags(User::NEW_EMAIL_CONFIRMED);
        $user4->save();
        $this->createSavedToken((int) $user4->getId(), UserToken::TYPE_CONFIRM_NEW_EMAIL);
        $result = $service->run('testcode_' . UserToken::TYPE_CONFIRM_NEW_EMAIL, $user4);
        self::assertTrue($result);
        self::assertSame('err_insecure@example.com', $user4->getEmail());
        self::assertSame('err_insecure_new@example.com', $user4->getUnconfirmedEmail());

        // Token expired even when email could change
        $config = VoytiConfigFactory::create(
            emailChangeConfirmation: EmailChangeConfirmation::NEW,
            tokenConfirmationLifespan: 100,
        );
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createMailService(new MailCapture()));
        $user5 = $this->createSavedUser(username: 'err_expchangeable');
        $user5->setEmail('err_expchangeable@example.com');
        $user5->setUnconfirmedEmail('err_expchangeable_new@example.com');
        $user5->save();
        $this->createSavedToken((int) $user5->getId(), UserToken::TYPE_CONFIRM_NEW_EMAIL, time() - 200000);
        $result = $service->run('testcode_' . UserToken::TYPE_CONFIRM_NEW_EMAIL, $user5);
        self::assertFalse($result);
        self::assertSame('err_expchangeable@example.com', $user5->getEmail());
    }

    public function testRunSecure(): void
    {
        $config = VoytiConfigFactory::create(
            emailChangeConfirmation: EmailChangeConfirmation::BOTH,
            tokenConfirmationLifespan: 999999,
        );
        $service = new EmailChangeService($config, new UserTokenFactory(), $this->createMailService(new MailCapture()));

        // New email token: sets flag and doesn't change email until old is confirmed
        $user1 = $this->createSavedUser(username: 'secure_new');
        $user1->setEmail('secure_new@example.com');
        $user1->setUnconfirmedEmail('secure_new_new@example.com');
        $user1->save();
        $this->createSavedToken((int) $user1->getId(), UserToken::TYPE_CONFIRM_NEW_EMAIL);
        $result = $service->run('testcode_' . UserToken::TYPE_CONFIRM_NEW_EMAIL, $user1);
        self::assertTrue($result);
        self::assertSame(User::NEW_EMAIL_CONFIRMED, $user1->getFlags());
        self::assertSame('secure_new@example.com', $user1->getEmail());

        // Old email token without new flag: sets old flag
        $user2 = $this->createSavedUser(username: 'secure_old');
        $user2->setEmail('secure_old@example.com');
        $user2->setUnconfirmedEmail('secure_old_new@example.com');
        $user2->save();
        $this->createSavedToken((int) $user2->getId(), UserToken::TYPE_CONFIRM_OLD_EMAIL);
        $result = $service->run('testcode_' . UserToken::TYPE_CONFIRM_OLD_EMAIL, $user2);
        self::assertTrue($result);
        self::assertSame(User::OLD_EMAIL_CONFIRMED, $user2->getFlags());

        // Both flags set: applies the email change
        $user3 = $this->createSavedUser(username: 'secure_both');
        $user3->setEmail('secure_both@example.com');
        $user3->setUnconfirmedEmail('secure_both_new@example.com');
        $user3->setFlags(User::NEW_EMAIL_CONFIRMED | User::OLD_EMAIL_CONFIRMED);
        $user3->save();
        $this->createSavedToken((int) $user3->getId(), UserToken::TYPE_CONFIRM_OLD_EMAIL);
        $result = $service->run('testcode_' . UserToken::TYPE_CONFIRM_OLD_EMAIL, $user3);
        self::assertTrue($result);
        self::assertSame('secure_both_new@example.com', $user3->getEmail());
        self::assertNull($user3->getUnconfirmedEmail());
        self::assertSame(0, $user3->getFlags());

        // Only old flag set when trying new token: doesn't change
        $user4 = $this->createSavedUser(username: 'secure_oldonly');
        $user4->setEmail('secure_oldonly@example.com');
        $user4->setUnconfirmedEmail('secure_oldonly_new@example.com');
        $user4->setFlags(User::OLD_EMAIL_CONFIRMED);
        $user4->save();
        $this->createSavedToken((int) $user4->getId(), UserToken::TYPE_CONFIRM_NEW_EMAIL);
        $result = $service->run('testcode_' . UserToken::TYPE_CONFIRM_NEW_EMAIL, $user4);
        self::assertTrue($result);
        self::assertSame('secure_oldonly@example.com', $user4->getEmail());
        self::assertSame('secure_oldonly_new@example.com', $user4->getUnconfirmedEmail());

        // New token with both flags already: doesn't change
        $user5 = $this->createSavedUser(username: 'secure_bothflag');
        $user5->setEmail('secure_bothflag@example.com');
        $user5->setUnconfirmedEmail('secure_bothflag_new@example.com');
        $user5->setFlags(User::NEW_EMAIL_CONFIRMED | User::OLD_EMAIL_CONFIRMED);
        $user5->save();
        $this->createSavedToken((int) $user5->getId(), UserToken::TYPE_CONFIRM_NEW_EMAIL);
        $result = $service->run('testcode_' . UserToken::TYPE_CONFIRM_NEW_EMAIL, $user5);
        self::assertTrue($result);
        self::assertSame('secure_bothflag@example.com', $user5->getEmail());
        self::assertSame('secure_bothflag_new@example.com', $user5->getUnconfirmedEmail());
        self::assertSame(User::NEW_EMAIL_CONFIRMED | User::OLD_EMAIL_CONFIRMED, $user5->getFlags());
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
