<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Helper;

use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use YiiRocks\Recaptcha\RecaptchaClient;
use YiiRocks\Recaptcha\RecaptchaConfig;
use YiiRocks\Recaptcha\RecaptchaRegistry;
use YiiRocks\Voyti\Form\Auth\RegistrationForm;
use YiiRocks\Voyti\Helper\RecaptchaHelper;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\Message\Php\MessageSource;
use Yiisoft\Translator\SimpleMessageFormatter;
use Yiisoft\Translator\Translator;
use Yiisoft\Translator\TranslatorInterface;

final class RecaptchaHelperTest extends TestCase
{
    protected function tearDown(): void
    {
        RecaptchaRegistry::reset();
        parent::tearDown();
    }

    public function testIsAvailableReturnsTrueWhenPackageInstalled(): void
    {
        $this->assertTrue(RecaptchaHelper::isAvailable());
    }

    public function testRenderReturnsEmptyStringWhenRecaptchaDisabled(): void
    {
        $config = new ModuleConfig();
        $result = RecaptchaHelper::render(
            $this->createStub(\Yiisoft\FormModel\FormModelInterface::class),
            $config,
        );

        $this->assertSame('', $result);
    }

    public function testRenderReturnsV2MarkupContainingSiteKeyWhenVersionIsV2(): void
    {
        RecaptchaRegistry::configure($this->createRecaptchaClient('site-key-v2', 'site-key-v3'));

        $config = new ModuleConfig(recaptchaVersion: 'v2');
        $form = new RegistrationForm($config, $this->createTranslator());

        $result = RecaptchaHelper::render($form, $config);

        $this->assertStringContainsString('data-sitekey="site-key-v2"', $result);
        $this->assertStringContainsString('g-recaptcha', $result);
    }

    public function testRenderReturnsV3MarkupContainingSiteKeyWhenVersionIsV3(): void
    {
        RecaptchaRegistry::configure($this->createRecaptchaClient('site-key-v2', 'site-key-v3'));

        $config = new ModuleConfig(recaptchaVersion: 'v3');
        $form = new RegistrationForm($config, $this->createTranslator());

        $result = RecaptchaHelper::render($form, $config);

        $this->assertStringContainsString('site-key-v3', $result);
        $this->assertStringContainsString('voyti_register', $result);
    }

    private function createRecaptchaClient(string $siteKeyV2, string $siteKeyV3): RecaptchaClient
    {
        $httpClient = new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw new RuntimeException('not used');
            }
        };
        $requestFactory = new class implements RequestFactoryInterface {
            public function createRequest(string $method, $uri): RequestInterface
            {
                throw new RuntimeException('not used');
            }
        };
        $streamFactory = new class implements StreamFactoryInterface {
            public function createStream(string $content = ''): StreamInterface
            {
                throw new RuntimeException('not used');
            }

            public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
            {
                throw new RuntimeException('not used');
            }

            public function createStreamFromResource($resource): StreamInterface
            {
                throw new RuntimeException('not used');
            }
        };

        return new RecaptchaClient(
            new RecaptchaConfig(siteKeyV2: $siteKeyV2, siteKeyV3: $siteKeyV3),
            $httpClient,
            $requestFactory,
            $streamFactory,
        );
    }

    private function createTranslator(): TranslatorInterface
    {
        $translator = new Translator('en', null, 'voyti');
        $translator->addCategorySources(
            new CategorySource(
                'voyti',
                new MessageSource(dirname(__DIR__, 2) . '/src/resources/messages'),
                new SimpleMessageFormatter(),
            ),
        );
        return $translator;
    }
}
