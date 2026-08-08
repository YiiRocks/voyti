<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Helper\FlashType;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;

final class RedirectTraitTest extends TestCase
{
    public function testRedirectWithFlashSetsSuccessFlashAndRedirects(): void
    {
        $flash = $this->createMock(FlashInterface::class);
        $flash->expects($this->once())->method('set')->with(FlashType::SUCCESS, 'Saved.');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('translate')->willReturn('Saved.');

        $fixture = $this->makeFixture($flash, $translator);
        $result = $fixture->callRedirectWithFlash('/account', 'voyti.settings.saved');

        self::assertSame(302, $result->getStatusCode());
        self::assertSame('/account', $result->getHeaderLine('Location'));
    }

    private function makeFixture(FlashInterface $flash, TranslatorInterface $translator): object
    {
        return new class (new Psr17Factory(), $flash, $translator) {
            use RedirectTrait;

            public function __construct(
                private ResponseFactoryInterface $responseFactory,
                private FlashInterface $flash,
                private TranslatorInterface $translator,
            ) {}

            public function callRedirectWithFlash(string $url, string $messageKey): ResponseInterface
            {
                return $this->redirectWithFlash($url, $messageKey);
            }
        };
    }
}
