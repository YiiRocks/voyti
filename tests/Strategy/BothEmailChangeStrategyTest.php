<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Strategy;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Model\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Strategy\BothEmailChangeStrategy;
use YiiRocks\Voyti\Strategy\NewEmailChangeStrategy;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\MailCapture;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Translator\TranslatorInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class BothEmailChangeStrategyTest extends TestCase
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

    public function testRunReturnsFalseWhenDefaultFails(): void
    {
        $translator = $this->createTranslator();
        $form = new SettingsForm(new ModuleConfig(), $translator);
        // No user set -> NewEmailChangeStrategy returns false
        $tokenFactory = new UserTokenFactory();
        $mailCapture = new MailCapture();
        $urlGenerator = new FakeUrlGenerator();
        $mailService = new MailService($mailCapture, '/tmp', $translator, $urlGenerator, 'App');
        $newStrategy = new NewEmailChangeStrategy($form, $tokenFactory, $mailService);

        $strategy = new BothEmailChangeStrategy($form, $tokenFactory, $mailService, $newStrategy);

        $this->assertFalse($strategy->run());
    }

    public function testRunReturnsTrueOnSuccess(): void
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
        $newStrategy = new NewEmailChangeStrategy($form, $tokenFactory, $mailService);

        $strategy = new BothEmailChangeStrategy($form, $tokenFactory, $mailService, $newStrategy);

        $this->assertTrue($strategy->run());
        $this->assertCount(2, $mailCapture->getSentMessages());

        $this->assertSame(
            (int) $user->getId(),
            (int) ConnectionProvider::get()->createCommand(
                'SELECT "user_id" FROM "user_token" WHERE "type" = :type',
                ['type' => 3],
            )->queryScalar(),
        );
    }

    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('translate')->willReturnCallback(fn (string $id) => $id);
        return $translator;
    }

    private function createUser(string $email = 'old@example.com'): User
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail($email);
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();
        return $user;
    }
}
