<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\TwoFactor;

use chillerlan\Authenticator\Authenticator;
use Closure;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use YiiRocks\Voyti\Controller\TwoFactor\TwoFactorController;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserBackupCode;
use YiiRocks\Voyti\Service\TwoFactor\BackupCodeService;
use YiiRocks\Voyti\tests\Support\CurrentUserTrait;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\MailAssertionsTrait;
use YiiRocks\Voyti\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\User\CurrentUser;

#[AllowMockObjectsWithoutExpectations]
final class TwoFactorControllerTest extends DatabaseTestCase
{
    use CurrentUserTrait;
    use MailAssertionsTrait;
    use TestContainerTrait;
    use UserFactoryTrait;

    private ContainerInterface $container;
    private CurrentUser $currentUser;
    private FlashInterface&MockObject $flash;
    private PasswordHasher $passwordHasher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->currentUser = $this->createCurrentUser();
        $this->flash = $this->createMock(FlashInterface::class);
        $this->passwordHasher = TestPasswordHasherFactory::create();
    }

    public static function alreadyEnabledRedirectProvider(): iterable
    {
        yield 'email action' => [static fn(TwoFactorController $controller): mixed => $controller->email(new ServerRequest('GET', '/'))];
        yield 'google action' => [static fn(TwoFactorController $controller): mixed => $controller->google(new ServerRequest('GET', '/'))];
    }

    public function testTwoFactorDisableSendCodeSendsCodeAndRendersView(): void
    {
        $user = $this->createUser(authTfEnabled: true, authTfType: 'email', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $html = (string) $this->createController()->disableSendCode()->getBody();

        self::assertStringContainsString('Enter the verification code sent to your email', $html);
        $this->assertMailSent();
    }

    public function testTwoFactorDisableSendCodeWhenGoogleMethodRedirects(): void
    {
        $user = $this->createUser(authTfEnabled: true, authTfType: 'google', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $result = $this->createController()->disableSendCode();

        $this->assertSame(302, $result->getStatusCode());
        $this->assertNoMailSent();
    }

    public function testTwoFactorDisableWithInvalidEmailCodeShowsFormWithCodeSent(): void
    {
        $user = $this->createUser(authTfEnabled: true, authTfType: 'email', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $html = (string) $this->createController()->disable(code: 'wrong')->getBody();

        self::assertStringContainsString('alert alert-danger', $html);
        self::assertStringContainsString('Invalid verification code.', $html);
        self::assertStringContainsString('Enter the verification code sent to your email', $html);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertTrue($updated->isAuthTfEnabled());
    }

    public function testTwoFactorDisableWithInvalidGoogleCodeShowsFormWithoutCodeSent(): void
    {
        $user = $this->createUser(authTfEnabled: true, authTfType: 'google', authTfKey: null, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $html = (string) $this->createController()->disable(code: 'wrong')->getBody();

        self::assertStringContainsString('alert alert-danger', $html);
        // The translated CodeValidator message (needs the translator wired into the validator).
        self::assertStringContainsString('Two factor authentication is not configured.', $html);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertTrue($updated->isAuthTfEnabled());
    }

    public function testTwoFactorDisableWithValidBackupCodeDisablesAndRedirects(): void
    {
        $backupCodeService = new BackupCodeService($this->passwordHasher);

        $user = $this->createUser(authTfEnabled: true, authTfType: 'email', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $codes = $backupCodeService->generate($user);

        $this->currentUser->login($user);

        $result = $this->createController(backupCodeService: $backupCodeService)->disable(code: $codes[0]);

        $this->assertSame(302, $result->getStatusCode());
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertFalse($updated->isAuthTfEnabled());
        $this->assertFalse($backupCodeService->hasUnused($updated));
        $this->assertCount(0, UserBackupCode::query()->where(['user_id' => $updated->getIdOrZero()])->all());
    }

    public function testTwoFactorDisableWithValidEmailCodeDisablesAndRedirects(): void
    {
        $user = $this->createUser(authTfEnabled: true, authTfType: 'email', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $result = $this->createController()->disable(code: '123456');

        $this->assertSame(302, $result->getStatusCode());
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertFalse($updated->isAuthTfEnabled());
        $this->assertNull($updated->getAuthTfKey());
        $this->assertNull($updated->getAuthTfType());
    }

    #[DataProvider('alreadyEnabledRedirectProvider')]
    public function testTwoFactorEmailGoogleWhenAlreadyEnabledRedirects(Closure $action): void
    {
        $user = $this->createUser(authTfEnabled: true, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $result = $action($this->createController());

        $this->assertSame(302, $result->getStatusCode());
    }

    public function testTwoFactorEmailRendersFragmentWithFragmentHeader(): void
    {
        $user = $this->createUser(authTfEnabled: false, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $request = (new ServerRequest('GET', '/'))->withHeader('X-Requested-With', 'XMLHttpRequest');
        $html = (string) $this->createController()->email($request)->getBody();

        // The fragment shows the "send code" prompt (no code sent yet) without the full page shell.
        self::assertStringContainsString('A verification code will be sent to the email address below.', $html);
        self::assertStringNotContainsString('<h1>', $html);
        $this->assertNoMailSent();
    }

    public function testTwoFactorEmailRendersShellWithoutFragmentHeader(): void
    {
        $user = $this->createUser(authTfEnabled: false, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $html = (string) $this->createController()->email(new ServerRequest('GET', '/'))->getBody();

        self::assertStringContainsString('Two-Factor Authentication', $html);
        self::assertStringContainsString('A verification code will be sent to the email address below.', $html);
    }

    public function testTwoFactorEnableWhenAlreadyEnabledRedirects(): void
    {
        $user = $this->createUser(authTfEnabled: true, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $result = $this->createController()->enable(method: 'google', code: '123456');

        $this->assertSame(302, $result->getStatusCode());
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertTrue($updated->isAuthTfEnabled());
    }

    public function testTwoFactorEnableWithEmailCode(): void
    {
        $user = $this->createUser(authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $html = (string) $this->createController()->enable(method: 'email', code: '123456')->getBody();

        self::assertStringContainsString('Backup Codes', $html);
        self::assertSame(10, substr_count($html, 'font-monospace'));
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertTrue($updated->isAuthTfEnabled());
        $this->assertSame('email', $updated->getAuthTfType());
    }

    public function testTwoFactorEnableWithInvalidEmailCodeShowsFormWithCodeSent(): void
    {
        $user = $this->createUser(authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $html = (string) $this->createController()->enable(method: 'email', code: 'wrong')->getBody();

        self::assertStringContainsString('alert alert-danger', $html);
        self::assertStringContainsString('Invalid verification code.', $html);
        self::assertStringContainsString('Enter the verification code sent to your email', $html);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertFalse($updated->isAuthTfEnabled());
    }

    public function testTwoFactorEnableWithInvalidGoogleCodeShowsFormWithoutCodeSent(): void
    {
        $user = $this->createUser(authTfType: 'google', authTfKey: null, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $html = (string) $this->createController()->enable(method: 'google', code: 'wrong')->getBody();

        self::assertStringContainsString('Scan this QR code', $html);
        // With no secret yet, CodeValidator emits the translated "not configured" message.
        self::assertStringContainsString('Two factor authentication is not configured.', $html);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertFalse($updated->isAuthTfEnabled());
    }

    public function testTwoFactorEnableWithInvalidGoogleCodeSwitchesTypeFromEmail(): void
    {
        // A leftover email account switching to Google: an invalid code must still switch the method
        // and clear the stale 6-digit key so a fresh QR secret is issued.
        $user = $this->createUser(authTfEnabled: false, authTfType: 'email', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $html = (string) $this->createController()->enable(method: 'google', code: 'wrong')->getBody();

        self::assertStringContainsString('Scan this QR code', $html);
        self::assertStringNotContainsString('123456', $html);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertFalse($updated->isAuthTfEnabled());
        $this->assertSame('google', $updated->getAuthTfType());
    }

    public function testTwoFactorEnableWithValidGoogleCodeEnablesAndRedirects(): void
    {
        $secret = (new Authenticator())->createSecret();
        $authenticator = new Authenticator();
        $authenticator->setSecret($secret);
        $code = $authenticator->code();

        // Start from an unset method so enabling must switch the account over to Google.
        $user = $this->createUser(authTfType: null, authTfKey: $secret, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $html = (string) $this->createController()->enable(method: 'google', code: $code)->getBody();

        self::assertStringContainsString('Backup Codes', $html);
        self::assertSame(10, substr_count($html, 'font-monospace'));
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertTrue($updated->isAuthTfEnabled());
        $this->assertSame('google', $updated->getAuthTfType());
    }

    public function testTwoFactorGoogleRendersFragmentWithFragmentHeader(): void
    {
        $user = $this->createUser(authTfEnabled: false, authTfType: null, authTfKey: 'secret', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $request = (new ServerRequest('GET', '/'))->withHeader('X-Requested-With', 'XMLHttpRequest');
        $html = (string) $this->createController()->google($request)->getBody();

        self::assertStringContainsString('Scan this QR code', $html);
        self::assertStringContainsString('<svg', $html);
        self::assertStringNotContainsString('<h1>', $html);
    }

    public function testTwoFactorGoogleRendersShellWithoutFragmentHeader(): void
    {
        // A leftover 6-digit email code sits in auth_tf_key; switching to Google must clear it and
        // switch the method, so the stale code never surfaces as a TOTP secret.
        $user = $this->createUser(authTfEnabled: false, authTfType: 'email', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $html = (string) $this->createController()->google(new ServerRequest('GET', '/'))->getBody();

        self::assertStringContainsString('Two-Factor Authentication', $html);
        self::assertStringContainsString('Scan this QR code', $html);
        self::assertStringContainsString('<svg', $html);
        self::assertStringNotContainsString('123456', $html);
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertSame('google', $updated->getAuthTfType());
    }

    public function testTwoFactorIndexReportsHasBackupCodesWhenCodesExist(): void
    {
        $backupCodeService = new BackupCodeService($this->passwordHasher);

        $user = $this->createUser(authTfEnabled: true, authTfType: 'google', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $backupCodeService->generate($user);

        $this->currentUser->login($user);

        $html = (string) $this->createController($backupCodeService)->index()->getBody();

        self::assertStringContainsString('Regenerate Backup Codes', $html);
        self::assertStringNotContainsString('You have no backup codes remaining', $html);
    }

    public function testTwoFactorRegenerateBackupCodesWhenNotEnabledRedirects(): void
    {
        $user = $this->createUser(authTfEnabled: false, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $result = $this->createController()->regenerateBackupCodes();

        $this->assertSame(302, $result->getStatusCode());
    }

    public function testTwoFactorRegenerateBackupCodesWithInvalidCodeShowsForm(): void
    {
        $user = $this->createUser(authTfEnabled: true, authTfType: 'email', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $html = (string) $this->createController()->regenerateBackupCodes(code: 'wrong')->getBody();

        // Re-renders the 2FA settings page (email method) with the error, rather than issuing codes.
        self::assertStringContainsString('To disable two-factor authentication', $html);
        self::assertStringContainsString('Invalid verification code.', $html);
        self::assertStringNotContainsString('Backup Codes', $html);
    }

    public function testTwoFactorRegenerateBackupCodesWithInvalidGoogleCodeShowsForm(): void
    {
        $user = $this->createUser(authTfEnabled: true, authTfType: 'google', authTfKey: null, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $html = (string) $this->createController()->regenerateBackupCodes(code: 'wrong')->getBody();

        // The translated CodeValidator message requires the translator wired into the validator.
        self::assertStringContainsString('Two factor authentication is not configured.', $html);
        // The settings page (not the new-codes list) is re-rendered - no monospace code list.
        self::assertStringNotContainsString('font-monospace', $html);
    }

    public function testTwoFactorRegenerateBackupCodesWithValidCodeShowsNewCodes(): void
    {
        $user = $this->createUser(authTfEnabled: true, authTfType: 'email', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());

        $this->currentUser->login($user);

        $html = (string) $this->createController()->regenerateBackupCodes(code: '123456')->getBody();

        self::assertStringContainsString('Backup Codes', $html);
        self::assertSame(10, substr_count($html, 'font-monospace'));
    }

    public function testTwoFactorRenewDoesNotResetTypeWhenAlreadyGoogle(): void
    {
        $user = $this->createUser(authTfEnabled: false, authTfType: 'google', authTfKey: 'secret', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $result = $this->createController()->renew();

        $this->assertSame(200, $result->getStatusCode());
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertSame('google', $updated->getAuthTfType());
    }

    public function testTwoFactorRenewGeneratesNewSecret(): void
    {
        $user = $this->createUser(authTfEnabled: false, authTfType: 'email', authTfKey: 'new-secret', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $result = $this->createController()->renew();

        $this->assertSame(200, $result->getStatusCode());
        $decoded = json_decode((string) $result->getBody(), true);
        self::assertIsArray($decoded);
        self::assertStringContainsString('<svg', (string) $decoded['qrCodeUri']);
        // regenerateQrCodeSvg rotates the secret, so it differs from the original and matches the persisted value.
        self::assertNotSame('new-secret', $decoded['secret']);
        $reloaded = User::findById((int) $user->getId());
        self::assertSame($reloaded?->getAuthTfKey(), $decoded['secret']);
        // Renewing from the email method switches the account over to the Google method.
        self::assertSame('google', $reloaded?->getAuthTfType());
    }

    public function testTwoFactorRenewWhenAlreadyEnabledReturnsError(): void
    {
        $user = $this->createUser(authTfEnabled: true, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $result = $this->createController()->renew();

        $this->assertSame(403, $result->getStatusCode());
        $decoded = json_decode((string) $result->getBody(), true);
        self::assertIsArray($decoded);
        self::assertSame('Two-factor authentication is already enabled.', $decoded['error']);
    }

    public function testTwoFactorSendEmailCodeDoesNotResetTypeWhenAlreadyEmail(): void
    {
        $user = $this->createUser(authTfEnabled: false, authTfType: 'email', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        (string) $this->createController()->sendEmailCode()->getBody();

        $this->assertMailSent();
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertSame('email', $updated->getAuthTfType());
    }

    public function testTwoFactorSendEmailCodeSendsCodeAndRendersView(): void
    {
        $user = $this->createUser(authTfEnabled: false, authTfType: null, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $html = (string) $this->createController()->sendEmailCode()->getBody();

        self::assertStringContainsString('Enter the verification code sent to your email', $html);
        $this->assertMailSent();
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertSame('email', $updated->getAuthTfType());
    }

    public function testTwoFactorSendEmailCodeWhenAlreadyEnabledRedirects(): void
    {
        $user = $this->createUser(authTfEnabled: true, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $result = $this->createController()->sendEmailCode();

        $this->assertSame(302, $result->getStatusCode());
        $this->assertNoMailSent();
    }

    public function testTwoFactorWhenAlreadyEnabledShowsSettings(): void
    {
        // An enabled email account: index must honour the stored method (email), not default to google.
        $user = $this->createUser(authTfEnabled: true, authTfType: 'email', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $html = (string) $this->createController()->index()->getBody();

        self::assertStringContainsString('To disable two-factor authentication', $html);
    }

    public function testTwoFactorWhenNotEnabledRendersShellWithoutPreloadingContent(): void
    {
        $user = $this->createUser(authTfEnabled: false, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $html = (string) $this->createController()->index()->getBody();

        // The not-enabled shell shows the loading spinner instead of preloaded method content.
        self::assertStringContainsString('Two-Factor Authentication', $html);
        self::assertStringContainsString('Loading', $html);
        self::assertStringNotContainsString('Scan this QR code', $html);
    }

    private function createController(?BackupCodeService $backupCodeService = null): TwoFactorController
    {
        $overrides = [
            CurrentUser::class => $this->currentUser,
            FlashInterface::class => $this->flash,
        ];

        if ($backupCodeService !== null) {
            $overrides[BackupCodeService::class] = $backupCodeService;
        }

        $this->container = $this->getTestContainer($overrides);

        return $this->container->get(TwoFactorController::class);
    }
}
