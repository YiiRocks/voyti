<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Service\FlashNotifier;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;

final class RedirectTraitTest extends TestCase
{
    public function testRedirectWithFlashQueuesSuccessMessageAndRedirects(): void
    {
        $flash = $this->createMock(FlashInterface::class);
        $flash->expects($this->once())->method('add')->with('toast.success', 'Saved.');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('translate')->willReturn('Saved.');

        $fixture = $this->makeFixture(new FlashNotifier($flash), $translator);
        $result = $fixture->callRedirectWithFlash('/account', 'voyti.settings.saved');

        self::assertSame(302, $result->getStatusCode());
        self::assertSame('/account', $result->getHeaderLine('Location'));
    }

    private function makeFixture(FlashNotifier $toast, TranslatorInterface $translator): object
    {
        return new class (new Psr17Factory(), $toast, $translator) {
            use RedirectTrait;

            public function __construct(
                private ResponseFactoryInterface $responseFactory,
                private FlashNotifier $toast,
                private TranslatorInterface $translator,
            ) {}

            public function callRedirectWithFlash(string $url, string $messageKey): ResponseInterface
            {
                return $this->redirectWithFlash($url, $messageKey);
            }
        };
    }
}
