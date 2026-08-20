<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Helper;

use Composer\InstalledVersions;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use YiiRocks\Recaptcha\RecaptchaRegistry;
use YiiRocks\Voyti\Enum\RecaptchaVersion;
use YiiRocks\Voyti\Helper\AgeHelper;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\Helper\LinkButtonHelper;
use YiiRocks\Voyti\Helper\LoginMetadataHelper;
use YiiRocks\Voyti\Helper\RecaptchaHelper;
use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Helper\ViewsPackageHelper;
use YiiRocks\Voyti\tests\Support\RecaptchaRegistryTrait;
use YiiRocks\Voyti\tests\Support\SimpleAssignmentsStorage;
use YiiRocks\Voyti\tests\Support\SimpleItemsStorage;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Form\Field\ResetButton;
use Yiisoft\Form\Field\SubmitButton;
use Yiisoft\Form\Theme\ThemeContainer;
use Yiisoft\FormModel\FormModel;
use Yiisoft\FormModel\FormModelInterface;
use Yiisoft\Rbac\Assignment;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\Manager;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;
use Yiisoft\User\CurrentUser;

final class RecaptchaTestForm extends FormModel
{
    public string $gRecaptchaResponse = '';

    public function getFormName(): string
    {
        return 'recaptchaTestForm';
    }
}

#[AllowMockObjectsWithoutExpectations]
final class HelperTest extends TestCase
{
    use RecaptchaRegistryTrait;

    protected function setUp(): void
    {
        ThemeContainer::initialize();
        RecaptchaRegistry::reset();
    }

    protected function tearDown(): void
    {
        ThemeContainer::initialize();
        RecaptchaRegistry::reset();
    }

    public static function ageCalculateProvider(): iterable
    {
        yield 'birthday already passed this year' => ['-10 years -1 day', 10];
        yield 'birthday is exactly today' => ['-10 years', 10];
        yield 'birthday not yet reached this year' => ['-10 years +1 day', 9];
        yield 'future birthday' => ['+10 years', null];
    }

    public static function authGetRuleNamesProvider(): iterable
    {
        yield 'empty items' => [[], []];
        yield 'unique rules' => [
            [
                'admin' => (new Role('admin'))->withRuleName('isAdmin'),
                'editor' => (new Role('editor'))->withRuleName('isEditor'),
                'write' => (new Permission('write'))->withRuleName('canWrite'),
                'user' => new Role('user'),
            ],
            ['isAdmin', 'isEditor', 'canWrite'],
        ];
        yield 'duplicate rule names' => [
            [
                'admin' => (new Role('admin'))->withRuleName('isAdmin'),
                'superadmin' => (new Role('superadmin'))->withRuleName('isAdmin'),
            ],
            ['isAdmin'],
        ];
    }

    public static function authHasRoleProvider(): iterable
    {
        yield 'role missing' => [[], false];
        yield 'role exists' => [['admin' => new Role('admin'), 'editor' => new Role('editor')], true];
    }

    public static function authIsAdminWithGivenUserIdProvider(): iterable
    {
        yield 'user is admin' => [3, ['admin' => new Permission('admin')], true];
        yield 'user not admin' => [2, [], false];
    }

    public static function buttonClassProvider(): iterable
    {
        yield 'submit button ignores trailing arguments' => [SubmitButton::class, ['btn', 'btn-primary'], 'btn'];
        yield 'reset button ignores trailing arguments' => [ResetButton::class, ['btn', 'btn-secondary'], 'btn'];
        yield 'submit button without theme' => [SubmitButton::class, null, ''];
        yield 'reset button without theme' => [ResetButton::class, null, ''];
        yield 'reset button themed' => [ResetButton::class, ['btn btn-outline-primary'], 'btn btn-outline-primary'];
        yield 'submit button themed' => [SubmitButton::class, ['btn btn-primary'], 'btn btn-primary'];
    }

    public static function recaptchaRenderConfiguredProvider(): iterable
    {
        yield 'v2' => [RecaptchaVersion::V2, 'data-sitekey="v2-site-key"', 'grecaptcha.execute', null];
        yield 'v3' => [RecaptchaVersion::V3, 'grecaptcha.execute', 'data-sitekey="v2-site-key"', '"action":"voyti_recaptchaTestForm"'];
    }

    public static function recaptchaRenderWithoutConfiguredKeyProvider(): iterable
    {
        yield 'v2' => [RecaptchaVersion::V2];
        yield 'v3' => [RecaptchaVersion::V3];
    }

    public static function remoteAddrProvider(): iterable
    {
        yield 'empty' => [['REMOTE_ADDR' => ''], '127.0.0.1'];
        yield 'missing' => [[], '127.0.0.1'];
        yield 'not string' => [['REMOTE_ADDR' => 12345], '127.0.0.1'];
        yield 'valid' => [['REMOTE_ADDR' => '203.0.113.9'], '203.0.113.9'];
    }

    #[DataProvider('ageCalculateProvider')]
    public function testAgeCalculate(string $birthdayModifier, ?int $expected): void
    {
        $birthday = (new DateTimeImmutable())->modify($birthdayModifier);
        self::assertSame($expected, AgeHelper::calculate($birthday));
    }

    public function testAgeCalculateReturnsNullForNull(): void
    {
        self::assertNull(AgeHelper::calculate(null));
    }

    #[DataProvider('authGetRuleNamesProvider')]
    public function testAuthGetRuleNames(array $items, array $expectedRules): void
    {
        $itemsStorage = $this->createMock(ItemsStorageInterface::class);
        $itemsStorage->expects(self::once())->method('getAll')->willReturn($items);

        $helper = $this->createAuthHelper(itemsStorage: $itemsStorage);

        self::assertSame($expectedRules, $helper->getRuleNames());
    }

    public function testAuthGetUnassignedItemsFiltersAssigned(): void
    {
        $items = [
            'read' => new Permission('read'),
            'write' => new Permission('write'),
            'admin' => new Role('admin'),
        ];

        $assigned = [new Assignment('1', 'read', 1234567890)];

        $assignmentsStorage = $this->createMock(AssignmentsStorageInterface::class);
        $assignmentsStorage->expects(self::once())->method('getByUserId')->with('1')->willReturn($assigned);

        $itemsStorage = $this->createMock(ItemsStorageInterface::class);
        $itemsStorage->expects(self::once())->method('getAll')->willReturn($items);

        $helper = $this->createAuthHelper(
            itemsStorage: $itemsStorage,
            assignmentsStorage: $assignmentsStorage,
        );

        $unassigned = $helper->getUnassignedItems(1);
        self::assertCount(2, $unassigned);
        self::assertArrayHasKey('write', $unassigned);
        self::assertArrayHasKey('admin', $unassigned);
        self::assertArrayNotHasKey('read', $unassigned);
    }

    #[DataProvider('authHasRoleProvider')]
    public function testAuthHasRole(array $userItems, bool $expected): void
    {
        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects(self::once())->method('getRolesByUserId')->with(1)->willReturn($userItems);

        $helper = $this->createAuthHelper(authManager: $authManager);

        self::assertSame($expected, $helper->hasRole(1, 'admin'));
    }

    public function testAuthIsAdminWithNullUserIdReturnsFalseWhenNoCurrentUser(): void
    {
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $currentUser = new CurrentUser($identityRepository, $eventDispatcher);

        $helper = $this->createAuthHelper(currentUser: $currentUser);

        self::assertFalse($helper->isAdmin());
    }

    #[DataProvider('buttonClassProvider')]
    public function testLinkButtonClass(string $fieldClass, ?array $buttonClass, string $expected): void
    {
        if ($buttonClass !== null) {
            ThemeContainer::initialize(
                ['default' => ['fieldConfigs' => [$fieldClass => ['buttonClass()' => $buttonClass]]]],
                'default',
            );
        }

        self::assertSame(
            $expected,
            $fieldClass === SubmitButton::class ? LinkButtonHelper::submitButtonClass() : LinkButtonHelper::resetButtonClass(),
        );
    }

    #[DataProvider('remoteAddrProvider')]
    public function testLoginMetadataRemoteAddr(array $serverParams, string $expected): void
    {
        self::assertSame($expected, LoginMetadataHelper::remoteAddr($serverParams));
    }

    #[DataProvider('userAgentProvider')]
    public function testLoginMetadataUserAgent(array $serverParams, ?string $expected): void
    {
        self::assertSame($expected, LoginMetadataHelper::userAgent($serverParams));
    }

    public function testRecaptchaIsAvailableReturnsTrue(): void
    {
        self::assertTrue(RecaptchaHelper::isAvailable());
    }

    #[DataProvider('recaptchaRenderWithoutConfiguredKeyProvider')]
    public function testRecaptchaRenderReturnsEmptyStringWhenSecretMissing(RecaptchaVersion $version): void
    {
        $this->configureRecaptchaRegistryWithoutSecret();

        $config = VoytiConfigFactory::create(recaptchaVersion: $version);
        $form = $this->createMock(FormModelInterface::class);

        self::assertSame('', RecaptchaHelper::render($form, $config));
    }

    #[DataProvider('recaptchaRenderConfiguredProvider')]
    public function testRecaptchaRenderWithConfiguredKey(RecaptchaVersion $version, string $shouldContain, string $shouldNotContain, ?string $alsoContains): void
    {
        $this->configureRecaptchaRegistry();

        $config = VoytiConfigFactory::create(recaptchaVersion: $version);
        $form = new RecaptchaTestForm();

        $html = RecaptchaHelper::render($form, $config);

        self::assertStringContainsString($shouldContain, $html);
        self::assertStringNotContainsString($shouldNotContain, $html);

        if ($alsoContains !== null) {
            self::assertStringContainsString($alsoContains, $html);
        }
    }

    #[DataProvider('timezoneLocalizedDateFormatProvider')]
    public function testTimezoneFormatLocalizedUsesLocaleDateFormat(string $locale, string $expectedStart): void
    {
        $formatted = TimezoneHelper::formatLocalized(1700000000, $locale);
        self::assertStringStartsWith($expectedStart, $formatted);
        self::assertStringEndsWith('22:13:20', $formatted);
    }

    public function testTimezoneFormatLocalizedWithInvalidLocaleFallsBackToRfc1123(): void
    {
        $timestamp = 1700000000;
        self::assertSame(date(DATE_RFC1123, $timestamp), TimezoneHelper::formatLocalized($timestamp, 'not-a-locale'));
    }

    public function testTimezoneFormatLocalizedWithInvalidTimezoneIsIgnored(): void
    {
        $timestamp = 1700000000;
        $withInvalidTimezone = TimezoneHelper::formatLocalized($timestamp, 'en', 'Invalid/Timezone');
        $withoutTimezone = TimezoneHelper::formatLocalized($timestamp, 'en');
        self::assertSame($withoutTimezone, $withInvalidTimezone);
    }

    public function testTimezoneFormatLocalizedWithRegionalLocaleUsesTimezoneRegionFormat(): void
    {
        $formatted = TimezoneHelper::formatLocalized(1700000000, 'en_GB', 'America/New_York');
        self::assertStringStartsWith('Nov 14, 2023, 5:13:20', $formatted);
        self::assertMatchesRegularExpression('/PM$/u', $formatted);
    }

    public function testTimezoneGetAllFormatsNegativeHalfHourOffsetCorrectly(): void
    {
        $timezones = TimezoneHelper::getAll();

        self::assertArrayHasKey('Pacific/Marquesas', $timezones);
        self::assertStringStartsWith('(GMT-9:30)', $timezones['Pacific/Marquesas']);
    }

    public function testTimezoneGetAllReturnsWellFormedTimezoneList(): void
    {
        $timezones = TimezoneHelper::getAll();

        self::assertIsArray($timezones);
        self::assertNotEmpty($timezones);

        self::assertArrayHasKey('UTC', $timezones);
        self::assertStringContainsString('UTC', $timezones['UTC']);
        self::assertStringContainsString('GMT', $timezones['UTC']);

        $sorted = $timezones;
        asort($sorted);
        self::assertSame($sorted, $timezones);

        foreach ($timezones as $key => $value) {
            self::assertTrue(in_array($key, DateTimeZone::listIdentifiers(), true), "{$key} is not a valid timezone");
            self::assertIsString($key);
            self::assertIsString($value);
            self::assertStringStartsWith('(GMT', $value);
            self::assertStringEndsWith($key, $value);
        }
    }

    #[DataProvider('timezoneIsValidProvider')]
    public function testTimezoneIsValid(string $timezone, bool $expected): void
    {
        self::assertSame($expected, TimezoneHelper::isValid($timezone));
    }

    public function testViewsPackagePathReturnsInstalledViewsPackagePath(): void
    {
        self::assertSame(
            InstalledVersions::getInstallPath('yiirocks/voyti-views-bootstrap5') . '/views',
            ViewsPackageHelper::viewsPath(),
        );
    }

    public function testViewsPackagePathThrowsWhenMultipleViewsPackagesAreInstalled(): void
    {
        $this->withInstalledPackage('yiirocks/voyti-views-tailwind', function (): void {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage(
                'Multiple packages provide "yiirocks/voyti-views": '
                . 'yiirocks/voyti-views-bootstrap5, yiirocks/voyti-views-tailwind. '
                . 'Only one views package may be installed at a time.',
            );

            ViewsPackageHelper::viewsPath();
        });
    }

    public static function timezoneIsValidProvider(): iterable
    {
        yield 'empty string' => ['', false];
        yield 'invalid timezone' => ['Invalid/Timezone', false];
        yield 'UTC' => ['UTC', true];
        yield 'America/New_York' => ['America/New_York', true];
        yield 'Europe/London' => ['Europe/London', true];
    }

    public static function timezoneLocalizedDateFormatProvider(): iterable
    {
        yield 'Dutch' => ['nl', '14 nov 2023'];
        yield 'German' => ['de', '14.11.2023'];
        yield 'Russian' => ['ru', '14 нояб'];
        yield 'Spanish' => ['es', '14 nov 2023'];
    }

    public static function userAgentProvider(): iterable
    {
        yield 'empty' => [['HTTP_USER_AGENT' => ''], null];
        yield 'missing' => [[], null];
        yield 'not string' => [['HTTP_USER_AGENT' => 12345], null];
        yield 'valid' => [['HTTP_USER_AGENT' => 'TestAgent'], 'TestAgent'];
    }

    private function createAuthHelper(
        ?ManagerInterface $authManager = null,
        ?ItemsStorageInterface $itemsStorage = null,
        ?AssignmentsStorageInterface $assignmentsStorage = null,
        ?VoytiConfig $config = null,
        ?CurrentUser $currentUser = null,
    ): AuthHelper {
        $currentUser ??= new CurrentUser(
            $this->createMock(IdentityRepositoryInterface::class),
            $this->createMock(EventDispatcherInterface::class),
        );
        return new AuthHelper(
            $authManager ?? $this->createMock(ManagerInterface::class),
            $itemsStorage ?? $this->createMock(ItemsStorageInterface::class),
            $assignmentsStorage ?? $this->createMock(AssignmentsStorageInterface::class),
            $config ?? VoytiConfigFactory::create(),
            $currentUser,
        );
    }

    private function createAuthHelperWithRealManager(
        SimpleItemsStorage $itemsStorage,
        SimpleAssignmentsStorage $assignmentsStorage,
    ): AuthHelper {
        return $this->createAuthHelper(
            authManager: new Manager($itemsStorage, $assignmentsStorage),
            itemsStorage: $itemsStorage,
            assignmentsStorage: $assignmentsStorage,
            config: VoytiConfigFactory::create(administratorPermissionName: 'admin'),
        );
    }

    /**
     * Temporarily fakes an installed Composer package via `InstalledVersions::reload()` (the
     * mechanism Composer itself documents for this) so a package-detection code path can be
     * exercised without actually requiring the package.
     */
    private function withInstalledPackage(string $packageName, callable $callback): void
    {
        $original = InstalledVersions::getAllRawData()[0];

        InstalledVersions::reload([
            'root' => $original['root'],
            'versions' => $original['versions'] + [
                $packageName => [
                    'pretty_version' => '1.0.0',
                    'version' => '1.0.0.0',
                    'reference' => null,
                    'type' => 'library',
                    'install_path' => __DIR__,
                    'aliases' => [],
                    'dev_requirement' => false,
                ],
            ],
        ]);

        try {
            $callback();
        } finally {
            InstalledVersions::reload($original);
        }
    }
}
