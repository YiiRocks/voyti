<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Strategy;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Model\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Strategy\NewEmailChangeStrategy;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\MailCapture;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Translator\TranslatorInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class NewEmailChangeStrategyTest extends TestCase
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

    public function testRunPersistsTokenWithRealUserIdWhenUserSaved(): void
    {
        $user = $this->createUser();
        $translator = $this->createTranslator();

        $form = new SettingsForm(new ModuleConfig(), $translator);
        $form->setUser($user);
        $form->email = 'new@example.com';

        $tokenFactory = new UserTokenFactory();

        $mailCapture = new MailCapture();
        $urlGenerator = new FakeUrlGenerator();
        $mailService = new MailService(
            $mailCapture,
            __DIR__ . '/../../resources/mail',
            $translator,
            $urlGenerator,
            'App',
        );

        $strategy = new NewEmailChangeStrategy($form, $tokenFactory, $mailService);

        $this->assertTrue($strategy->run());

        $this->assertSame(
            (int) $user->getId(),
            (int) ConnectionProvider::get()->createCommand(
                'SELECT "user_id" FROM "user_token" WHERE "type" = :type',
                ['type' => 2],
            )->queryScalar(),
        );
    }

    public function testRunPersistsTokenWithZeroUserIdWhenUserUnsaved(): void
    {
        $translator = $this->createTranslator();

        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('old@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $this->assertNull($user->getId());

        $form = new SettingsForm(new ModuleConfig(), $translator);
        $form->setUser($user);
        $form->email = 'new@example.com';

        $tokenFactory = new UserTokenFactory();

        $mailCapture = new MailCapture();
        $urlGenerator = new FakeUrlGenerator();
        $mailService = new MailService(
            $mailCapture,
            __DIR__ . '/../../resources/mail',
            $translator,
            $urlGenerator,
            'App',
        );

        $strategy = new NewEmailChangeStrategy($form, $tokenFactory, $mailService);

        $this->assertTrue($strategy->run());

        $this->assertSame(
            0,
            (int) ConnectionProvider::get()->createCommand(
                'SELECT "user_id" FROM "user_token" WHERE "type" = :type',
                ['type' => 2],
            )->queryScalar(),
        );
    }

    public function testRunReturnsFalseWhenUserIsNull(): void
    {
        $translator = $this->createTranslator();
        $form = new SettingsForm(new ModuleConfig(), $translator);
        $mailCapture = new MailCapture();
        $urlGenerator = new FakeUrlGenerator();
        $mailService = new MailService($mailCapture, '/tmp', $translator, $urlGenerator, 'App');
        $tokenFactory = new UserTokenFactory();

        $strategy = new NewEmailChangeStrategy($form, $tokenFactory, $mailService);

        $this->assertFalse($strategy->run());
    }

    public function testRunReturnsTrueWhenMailSucceeds(): void
    {
        $user = $this->createUser();
        $translator = $this->createTranslator();

        $form = new SettingsForm(new ModuleConfig(), $translator);
        $form->setUser($user);
        $form->email = 'new@example.com';

        $tokenFactory = new UserTokenFactory();

        $mailCapture = new MailCapture();
        $urlGenerator = new FakeUrlGenerator();
        $mailService = new MailService(
            $mailCapture,
            __DIR__ . '/../../resources/mail',
            $translator,
            $urlGenerator,
            'App',
        );

        $strategy = new NewEmailChangeStrategy($form, $tokenFactory, $mailService);

        $this->assertTrue($strategy->run());
        $this->assertSame('new@example.com', $user->getUnconfirmedEmail());
        $this->assertCount(1, $mailCapture->getSentMessages());

        $this->assertSame(
            'new@example.com',
            ConnectionProvider::get()->createCommand(
                'SELECT "unconfirmed_email" FROM "user" WHERE "id" = :id',
                ['id' => $user->getId()],
            )->queryScalar(),
        );
    }

    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('translate')->willReturnCallback(fn (string $id) => $id);
        return $translator;
    }

    private function createUser(): User
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
