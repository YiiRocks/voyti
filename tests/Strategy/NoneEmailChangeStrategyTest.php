<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Strategy;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Strategy\NoneEmailChangeStrategy;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Translator\TranslatorInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class NoneEmailChangeStrategyTest extends TestCase
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

    public function testRunReturnsFalseWhenUserIsNull(): void
    {
        $form = new SettingsForm(new ModuleConfig(), $this->createTranslator());
        $strategy = new NoneEmailChangeStrategy($form);

        $this->assertFalse($strategy->run());
    }

    public function testRunSetsEmailAndSaves(): void
    {
        $user = $this->createUser();
        $form = new SettingsForm(new ModuleConfig(), $this->createTranslator());
        $form->setUser($user);
        $form->email = 'new@example.com';

        $strategy = new NoneEmailChangeStrategy($form);

        $this->assertTrue($strategy->run());
        $this->assertSame('new@example.com', $user->getEmail());

        $this->assertSame(
            'new@example.com',
            ConnectionProvider::get()->createCommand(
                'SELECT "email" FROM "user" WHERE "id" = :id',
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
