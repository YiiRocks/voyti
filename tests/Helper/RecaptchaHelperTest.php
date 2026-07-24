<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Helper;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use YiiRocks\Recaptcha\Exception\MissingSiteKeyException;
use YiiRocks\Recaptcha\RecaptchaClient;
use YiiRocks\Recaptcha\RecaptchaConfig;
use YiiRocks\Recaptcha\RecaptchaRegistry;
use YiiRocks\Voyti\Enum\RecaptchaVersion;
use YiiRocks\Voyti\Helper\RecaptchaHelper;
use YiiRocks\Voyti\tests\Support\ModuleConfigFactory;
use Yiisoft\FormModel\FormModel;
use Yiisoft\FormModel\FormModelInterface;

final class RecaptchaTestForm extends FormModel
{
    public string $gRecaptchaResponse = '';

    public function getFormName(): string
    {
        return 'recaptchaTestForm';
    }
}

#[AllowMockObjectsWithoutExpectations]
final class RecaptchaHelperTest extends TestCase
{
    protected function setUp(): void
    {
        RecaptchaRegistry::reset();
    }

    /**
     * @return iterable<string, array{RecaptchaVersion}>
     */
    public static function renderWithMissingSiteKeyProvider(): iterable
    {
        yield 'v2' => [RecaptchaVersion::V2];
        yield 'v3' => [RecaptchaVersion::V3];
    }

    public function testIsAvailableReturnsTrue(): void
    {
        self::assertTrue(RecaptchaHelper::isAvailable());
    }

    public function testRenderReturnsEmptyStringWhenRecaptchaVersionIsNull(): void
    {
        $config = ModuleConfigFactory::create(recaptchaVersion: null);
        $form = $this->createMock(FormModelInterface::class);

        self::assertSame('', RecaptchaHelper::render($form, $config));
    }

    public function testRenderV2ProducesV2MarkupWithConfiguredKey(): void
    {
        $client = $this->buildClient('v2-site-key', 'v3-site-key');
        RecaptchaRegistry::configure($client);

        $config = ModuleConfigFactory::create(recaptchaVersion: RecaptchaVersion::V2);
        $form = new RecaptchaTestForm();

        $html = RecaptchaHelper::render($form, $config);

        self::assertStringContainsString('data-sitekey="v2-site-key"', $html);
        self::assertStringNotContainsString('grecaptcha.execute', $html);
    }

    public function testRenderV3ProducesV3MarkupWithConfiguredKey(): void
    {
        $client = $this->buildClient('v2-site-key', 'v3-site-key');
        RecaptchaRegistry::configure($client);

        $config = ModuleConfigFactory::create(recaptchaVersion: RecaptchaVersion::V3);
        $form = new RecaptchaTestForm();

        $html = RecaptchaHelper::render($form, $config);

        self::assertStringContainsString('grecaptcha.execute', $html);
        self::assertStringContainsString('"action":"voyti_recaptchaTestForm"', $html);
        self::assertStringNotContainsString('data-sitekey="v2-site-key"', $html);
    }

    #[DataProvider('renderWithMissingSiteKeyProvider')]
    public function testRenderWithMissingSiteKeyThrowsMissingSiteKeyException(RecaptchaVersion $version): void
    {
        $config = ModuleConfigFactory::create(recaptchaVersion: $version);
        $form = $this->createMock(FormModelInterface::class);
        $form->method('getFormName')->willReturn('registerForm');

        $this->expectException(MissingSiteKeyException::class);

        RecaptchaHelper::render($form, $config);
    }

    private function buildClient(string $siteKeyV2, string $siteKeyV3): RecaptchaClient
    {
        $config = new RecaptchaConfig(
            siteKeyV2: $siteKeyV2,
            siteKeyV3: $siteKeyV3,
        );

        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);

        return new RecaptchaClient($config, $httpClient, $requestFactory, $streamFactory);
    }
}
