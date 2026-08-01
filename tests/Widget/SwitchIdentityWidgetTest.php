<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Widget;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Service\SwitchIdentityService;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\VoytiConfig;
use YiiRocks\Voyti\Widget\SwitchIdentityWidget;
use Yiisoft\Csrf\CsrfTokenInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\Message\Php\MessageSource;
use Yiisoft\Translator\SimpleMessageFormatter;
use Yiisoft\Translator\Translator;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;

final class SwitchIdentityWidgetTest extends TestCase
{
    use UserFactoryTrait;

    public function testRenderReturnsEmptyStringWhenNotSwitched(): void
    {
        $switchService = $this->createSwitchIdentityService(isSwitched: false);

        $widget = new SwitchIdentityWidget(
            $this->createCsrfToken(),
            $switchService,
            $this->createRealTranslator(),
            new FakeUrlGenerator(),
        );

        self::assertSame('', $widget->render());
    }

    public function testRenderReturnsEmptyStringWhenSwitchedButOriginalUserNotFound(): void
    {
        $switchService = $this->createSwitchIdentityService(isSwitched: true, originalUserId: null);

        $widget = new SwitchIdentityWidget(
            $this->createCsrfToken(),
            $switchService,
            $this->createRealTranslator(),
            new FakeUrlGenerator(),
        );

        self::assertSame('', $widget->render());
    }

    public function testRenderReturnsHtmlWhenSwitchedWithValidOriginalUser(): void
    {
        $originalUser = $this->buildUser(username: 'admin');

        $switchService = $this->createStub(SwitchIdentityService::class);
        $switchService->method('isSwitched')->willReturn(true);
        $switchService->method('getOriginalUser')->willReturn($originalUser);

        $widget = new SwitchIdentityWidget(
            $this->createCsrfToken('test-csrf-token'),
            $switchService,
            $this->createRealTranslator(),
            new FakeUrlGenerator(),
        );

        $html = $widget->render();

        self::assertStringContainsString('alert alert-warning', $html);
        self::assertStringContainsString('switch back to admin', $html);
        self::assertStringContainsString('Restore', $html);
        self::assertStringNotContainsString('voyti.view.admin.switched_banner', $html);
        self::assertStringNotContainsString('voyti.view.admin.restore_button', $html);
        self::assertStringContainsString('//voyti/admin-users-switch-identity-restore', $html);
        self::assertStringContainsString('btn-warning', $html);
        self::assertStringContainsString('test-csrf-token', $html);
    }

    private function createCsrfToken(string $value = 'csrf-token'): CsrfTokenInterface
    {
        $token = $this->createStub(CsrfTokenInterface::class);
        $token->method('getValue')->willReturn($value);
        return $token;
    }

    /**
     * A real translator whose default category is deliberately NOT 'voyti' (unlike
     * {@see \YiiRocks\Voyti\tests\TestCase::createTranslator()}), so a call site that forgets to
     * pass category: 'voyti' explicitly fails this test instead of passing by coincidence.
     */
    private function createRealTranslator(): TranslatorInterface
    {
        $translator = new Translator('en');
        $translator->addCategorySources(
            new CategorySource(
                'voyti',
                new MessageSource(dirname(__DIR__, 2) . '/resources/messages'),
                new SimpleMessageFormatter(),
            ),
        );
        return $translator;
    }

    private function createSwitchIdentityService(bool $isSwitched, ?int $originalUserId = null): SwitchIdentityService
    {
        $session = $this->createStub(SessionInterface::class);
        $session->method('has')->willReturn($isSwitched);
        if ($isSwitched && $originalUserId !== null) {
            $session->method('get')->willReturn($originalUserId);
        }

        $currentUser = $this->createStub(CurrentUser::class);

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);

        $config = $this->createStub(VoytiConfig::class);
        $config->enableSwitchIdentities = true;

        return new SwitchIdentityService($config, $currentUser, $session, $eventDispatcher);
    }
}
