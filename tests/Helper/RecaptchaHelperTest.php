<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Helper;

use PHPUnit\Framework\TestCase;
use YiiRocks\Recaptcha\RecaptchaRegistry;
use YiiRocks\Voyti\Enum\RecaptchaVersion;
use YiiRocks\Voyti\Helper\RecaptchaHelper;
use YiiRocks\Voyti\ModuleConfig;
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

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class RecaptchaHelperTest extends TestCase
{
    protected function setUp(): void
    {
        RecaptchaRegistry::reset();
    }

    public function testIsAvailableReturnsTrue(): void
    {
        self::assertTrue(RecaptchaHelper::isAvailable());
    }

    public function testRenderReturnsEmptyStringWhenRecaptchaVersionIsNull(): void
    {
        $config = new ModuleConfig(recaptchaVersion: null);
        $form = $this->createMock(FormModelInterface::class);

        self::assertSame('', RecaptchaHelper::render($form, $config));
    }

    public function testRenderV2ProducesV2MarkupWithConfiguredKey(): void
    {
        $client = $this->buildClient('v2-site-key', 'v3-site-key');
        RecaptchaRegistry::configure($client);

        $config = new ModuleConfig(recaptchaVersion: RecaptchaVersion::V2);
        $form = new RecaptchaTestForm();

        $html = RecaptchaHelper::render($form, $config);

        self::assertStringContainsString('data-sitekey="v2-site-key"', $html);
        self::assertStringNotContainsString('grecaptcha.execute', $html);
    }

    public function testRenderV3ProducesV3MarkupWithConfiguredKey(): void
    {
        $client = $this->buildClient('v2-site-key', 'v3-site-key');
        RecaptchaRegistry::configure($client);

        $config = new ModuleConfig(recaptchaVersion: RecaptchaVersion::V3);
        $form = new RecaptchaTestForm();

        $html = RecaptchaHelper::render($form, $config);

        self::assertStringContainsString('grecaptcha.execute', $html);
        self::assertStringContainsString('"action":"voyti_recaptchaTestForm"', $html);
        self::assertStringNotContainsString('data-sitekey="v2-site-key"', $html);
    }

    public function testRenderWithV2ThrowsMissingSiteKeyException(): void
    {
        $config = new ModuleConfig(recaptchaVersion: RecaptchaVersion::V2);
        $form = $this->createMock(FormModelInterface::class);
        $form->method('getFormName')->willReturn('registerForm');

        $this->expectException(\YiiRocks\Recaptcha\Exception\MissingSiteKeyException::class);

        RecaptchaHelper::render($form, $config);
    }

    public function testRenderWithV3ThrowsMissingSiteKeyException(): void
    {
        $config = new ModuleConfig(recaptchaVersion: RecaptchaVersion::V3);
        $form = $this->createMock(FormModelInterface::class);
        $form->method('getFormName')->willReturn('registerForm');

        $this->expectException(\YiiRocks\Recaptcha\Exception\MissingSiteKeyException::class);

        RecaptchaHelper::render($form, $config);
    }

    private function buildClient(string $siteKeyV2, string $siteKeyV3): \YiiRocks\Recaptcha\RecaptchaClient
    {
        $config = new \YiiRocks\Recaptcha\RecaptchaConfig(
            siteKeyV2: $siteKeyV2,
            siteKeyV3: $siteKeyV3,
        );

        $httpClient = $this->createMock(\Psr\Http\Client\ClientInterface::class);
        $requestFactory = $this->createMock(\Psr\Http\Message\RequestFactoryInterface::class);
        $streamFactory = $this->createMock(\Psr\Http\Message\StreamFactoryInterface::class);

        return new \YiiRocks\Recaptcha\RecaptchaClient($config, $httpClient, $requestFactory, $streamFactory);
    }
}
