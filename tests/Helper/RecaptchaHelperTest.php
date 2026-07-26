<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Helper;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiRocks\Recaptcha\RecaptchaRegistry;
use YiiRocks\Voyti\Enum\RecaptchaVersion;
use YiiRocks\Voyti\Helper\RecaptchaHelper;
use YiiRocks\Voyti\tests\Support\ModuleConfigFactory;
use YiiRocks\Voyti\tests\Support\RecaptchaRegistryTrait;
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
    use RecaptchaRegistryTrait;

    protected function setUp(): void
    {
        RecaptchaRegistry::reset();
    }

    protected function tearDown(): void
    {
        RecaptchaRegistry::reset();
    }

    /**
     * @return iterable<string, array{RecaptchaVersion}>
     */
    public static function renderWithoutConfiguredKeyProvider(): iterable
    {
        yield 'v2' => [RecaptchaVersion::V2];
        yield 'v3' => [RecaptchaVersion::V3];
    }

    public function testIsAvailableReturnsTrue(): void
    {
        self::assertTrue(RecaptchaHelper::isAvailable());
    }

    #[DataProvider('renderWithoutConfiguredKeyProvider')]
    public function testRenderReturnsEmptyStringWhenNotConfigured(RecaptchaVersion $version): void
    {
        $config = ModuleConfigFactory::create(recaptchaVersion: $version);
        $form = $this->createMock(FormModelInterface::class);

        self::assertSame('', RecaptchaHelper::render($form, $config));
    }

    #[DataProvider('renderWithoutConfiguredKeyProvider')]
    public function testRenderReturnsEmptyStringWhenSecretMissing(RecaptchaVersion $version): void
    {
        $this->configureRecaptchaRegistryWithoutSecret();

        $config = ModuleConfigFactory::create(recaptchaVersion: $version);
        $form = $this->createMock(FormModelInterface::class);

        self::assertSame('', RecaptchaHelper::render($form, $config));
    }

    public function testRenderV2ProducesV2MarkupWithConfiguredKey(): void
    {
        $this->configureRecaptchaRegistry();

        $config = ModuleConfigFactory::create(recaptchaVersion: RecaptchaVersion::V2);
        $form = new RecaptchaTestForm();

        $html = RecaptchaHelper::render($form, $config);

        self::assertStringContainsString('data-sitekey="v2-site-key"', $html);
        self::assertStringNotContainsString('grecaptcha.execute', $html);
    }

    public function testRenderV3ProducesV3MarkupWithConfiguredKey(): void
    {
        $this->configureRecaptchaRegistry();

        $config = ModuleConfigFactory::create(recaptchaVersion: RecaptchaVersion::V3);
        $form = new RecaptchaTestForm();

        $html = RecaptchaHelper::render($form, $config);

        self::assertStringContainsString('grecaptcha.execute', $html);
        self::assertStringContainsString('"action":"voyti_recaptchaTestForm"', $html);
        self::assertStringNotContainsString('data-sitekey="v2-site-key"', $html);
    }
}
