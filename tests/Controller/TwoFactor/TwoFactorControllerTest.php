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

    #[DataProvider('alreadyEnabledRedirectProvider')]
    public function testAlreadyEnabledRedirects(Closure $action): void
    {
        $user = $this->createUser(authTfEnabled: true, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $result = $action($this->createController());

        $this->assertSame(302, $result->getStatusCode());
    }

    public function testDisableSendCode(): void
    {
        // Email method: sends code and renders view
        $user1 = $this->createUser(username: 'disable_email', email: 'disable_email@example.com', authTfEnabled: true, authTfType: 'email', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user1);
        $html = (string) $this->createController()->disableSendCode()->getBody();
        self::assertStringContainsString('Enter the verification code sent to your email', $html);
        $this->assertMailSent();

        // Google method: redirects without sending
        $user2 = $this->createUser(username: 'disable_google', email: 'disable_google@example.com', authTfEnabled: true, authTfType: 'google', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user2);
        $result = $this->createController()->disableSendCode();
        $this->assertSame(302, $result->getStatusCode());
        $this->assertNoMailSent();
    }

    public function testDisableWithInvalidCode(): void
    {
        // Email method: shows form with code-sent prompt
        $user1 = $this->createUser(username: 'disable_invalid_email', email: 'disable_invalid_email@example.com', authTfEnabled: true, authTfType: 'email', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user1);
        $html = (string) $this->createController()->disable(code: 'wrong')->getBody();
        self::assertStringContainsString('alert alert-danger', $html);
        self::assertStringContainsString('Invalid verification code.', $html);
        self::assertStringContainsString('Enter the verification code sent to your email', $html);
        $updated1 = User::findById((int) $user1->getId());
        $this->assertNotNull($updated1);
        $this->assertTrue($updated1->isAuthTfEnabled());

        // Google method: shows form without code-sent prompt
        $user2 = $this->createUser(username: 'disable_invalid_google', email: 'disable_invalid_google@example.com', authTfEnabled: true, authTfType: 'google', authTfKey: null, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user2);
        $html = (string) $this->createController()->disable(code: 'wrong')->getBody();
        self::assertStringContainsString('alert alert-danger', $html);
        self::assertStringContainsString('Two factor authentication is not configured.', $html);
        $updated2 = User::findById((int) $user2->getId());
        $this->assertNotNull($updated2);
        $this->assertTrue($updated2->isAuthTfEnabled());
    }

    public function testDisableWithValidCode(): void
    {
        // Backup code: disables and deletes codes
        $backupCodeService = new BackupCodeService($this->passwordHasher);
        $user1 = $this->createUser(username: 'disable_backup', email: 'disable_backup@example.com', authTfEnabled: true, authTfType: 'email', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $codes = $backupCodeService->generate($user1);
        $this->currentUser->login($user1);
        $result = $this->createController(backupCodeService: $backupCodeService)->disable(code: $codes[0]);
        $this->assertSame(302, $result->getStatusCode());
        $updated1 = User::findById((int) $user1->getId());
        $this->assertNotNull($updated1);
        $this->assertFalse($updated1->isAuthTfEnabled());
        $this->assertFalse($backupCodeService->hasUnused($updated1));
        $this->assertCount(0, UserBackupCode::query()->where(['user_id' => $updated1->getIdOrZero()])->all());

        // Email code: disables and clears auth type/key
        $user2 = $this->createUser(username: 'disable_email_valid', email: 'disable_email_valid@example.com', authTfEnabled: true, authTfType: 'email', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user2);
        $result = $this->createController()->disable(code: '123456');
        $this->assertSame(302, $result->getStatusCode());
        $updated2 = User::findById((int) $user2->getId());
        $this->assertNotNull($updated2);
        $this->assertFalse($updated2->isAuthTfEnabled());
        $this->assertNull($updated2->getAuthTfKey());
        $this->assertNull($updated2->getAuthTfType());
    }

    public function testEmailRendering(): void
    {
        $user = $this->createUser(authTfEnabled: false, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        // Fragment with X-Requested-With header: no page shell
        $request = (new ServerRequest('GET', '/'))->withHeader('X-Requested-With', 'XMLHttpRequest');
        $html = (string) $this->createController()->email($request)->getBody();
        self::assertStringContainsString('A verification code will be sent to the email address below.', $html);
        self::assertStringNotContainsString('<h1>', $html);
        $this->assertNoMailSent();

        // Shell without fragment header: full page
        $html = (string) $this->createController()->email(new ServerRequest('GET', '/'))->getBody();
        self::assertStringContainsString('Two-Factor Authentication', $html);
        self::assertStringContainsString('A verification code will be sent to the email address below.', $html);
    }

    public function testEnableEmail(): void
    {
        // Valid email code: enables and shows backup codes
        $user1 = $this->createUser(username: 'enable_email_valid', email: 'enable_email_valid@example.com', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user1);
        $html = (string) $this->createController()->enable(method: 'email', code: '123456')->getBody();
        self::assertStringContainsString('Backup Codes', $html);
        self::assertSame(10, substr_count($html, 'font-monospace'));
        $updated1 = User::findById((int) $user1->getId());
        $this->assertNotNull($updated1);
        $this->assertTrue($updated1->isAuthTfEnabled());
        $this->assertSame('email', $updated1->getAuthTfType());

        // Invalid email code: shows form with code-sent prompt
        $user2 = $this->createUser(username: 'enable_email_invalid', email: 'enable_email_invalid@example.com', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user2);
        $html = (string) $this->createController()->enable(method: 'email', code: 'wrong')->getBody();
        self::assertStringContainsString('alert alert-danger', $html);
        self::assertStringContainsString('Invalid verification code.', $html);
        self::assertStringContainsString('Enter the verification code sent to your email', $html);
        $updated2 = User::findById((int) $user2->getId());
        $this->assertNotNull($updated2);
        $this->assertFalse($updated2->isAuthTfEnabled());
    }

    public function testEnableGoogle(): void
    {
        // Invalid google code without secret: shows form without code-sent prompt
        $user1 = $this->createUser(username: 'enable_google_invalid', email: 'enable_google_invalid@example.com', authTfType: 'google', authTfKey: null, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user1);
        $html = (string) $this->createController()->enable(method: 'google', code: 'wrong')->getBody();
        self::assertStringContainsString('Scan this QR code', $html);
        self::assertStringContainsString('Two factor authentication is not configured.', $html);
        $updated1 = User::findById((int) $user1->getId());
        $this->assertNotNull($updated1);
        $this->assertFalse($updated1->isAuthTfEnabled());

        // Invalid code switching from email: clears stale email key and switches method
        $user2 = $this->createUser(username: 'enable_google_switch', email: 'enable_google_switch@example.com', authTfEnabled: false, authTfType: 'email', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user2);
        $html = (string) $this->createController()->enable(method: 'google', code: 'wrong')->getBody();
        self::assertStringContainsString('Scan this QR code', $html);
        self::assertStringNotContainsString('123456', $html);
        $updated2 = User::findById((int) $user2->getId());
        $this->assertNotNull($updated2);
        $this->assertFalse($updated2->isAuthTfEnabled());
        $this->assertSame('google', $updated2->getAuthTfType());

        // Valid google code: enables and shows backup codes
        $secret = (new Authenticator())->createSecret();
        $authenticator = new Authenticator();
        $authenticator->setSecret($secret);
        $code = $authenticator->code();
        $user3 = $this->createUser(username: 'enable_google_valid', email: 'enable_google_valid@example.com', authTfType: null, authTfKey: $secret, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user3);
        $html = (string) $this->createController()->enable(method: 'google', code: $code)->getBody();
        self::assertStringContainsString('Backup Codes', $html);
        self::assertSame(10, substr_count($html, 'font-monospace'));
        $updated3 = User::findById((int) $user3->getId());
        $this->assertNotNull($updated3);
        $this->assertTrue($updated3->isAuthTfEnabled());
        $this->assertSame('google', $updated3->getAuthTfType());
    }

    public function testEnableWhenAlreadyEnabled(): void
    {
        $user = $this->createUser(authTfEnabled: true, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        $result = $this->createController()->enable(method: 'google', code: '123456');

        $this->assertSame(302, $result->getStatusCode());
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertTrue($updated->isAuthTfEnabled());
    }

    public function testGoogleRendering(): void
    {
        $user = $this->createUser(authTfEnabled: false, authTfType: null, authTfKey: 'secret', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user);

        // Fragment with X-Requested-With header: no page shell
        $request = (new ServerRequest('GET', '/'))->withHeader('X-Requested-With', 'XMLHttpRequest');
        $html = (string) $this->createController()->google($request)->getBody();
        self::assertStringContainsString('Scan this QR code', $html);
        self::assertStringContainsString('<svg', $html);
        self::assertStringNotContainsString('<h1>', $html);

        // Shell without fragment header: full page, switches from email to google if needed
        $user2 = $this->createUser(username: 'google_shell', email: 'google_shell@example.com', authTfEnabled: false, authTfType: 'email', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user2);
        $html = (string) $this->createController()->google(new ServerRequest('GET', '/'))->getBody();
        self::assertStringContainsString('Two-Factor Authentication', $html);
        self::assertStringContainsString('Scan this QR code', $html);
        self::assertStringContainsString('<svg', $html);
        self::assertStringNotContainsString('123456', $html);
        $updated2 = User::findById((int) $user2->getId());
        $this->assertNotNull($updated2);
        $this->assertSame('google', $updated2->getAuthTfType());
    }

    public function testIndex(): void
    {
        // Already enabled: shows settings with stored method
        $user1 = $this->createUser(username: 'index_enabled', email: 'index_enabled@example.com', authTfEnabled: true, authTfType: 'email', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user1);
        $html = (string) $this->createController()->index()->getBody();
        self::assertStringContainsString('To disable two-factor authentication', $html);

        // Not enabled: shows loading spinner without preloading method content
        $user2 = $this->createUser(username: 'index_disabled', email: 'index_disabled@example.com', authTfEnabled: false, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user2);
        $html = (string) $this->createController()->index()->getBody();
        self::assertStringContainsString('Two-Factor Authentication', $html);
        self::assertStringContainsString('Loading', $html);
        self::assertStringNotContainsString('Scan this QR code', $html);

        // With backup codes: shows regenerate option
        $backupCodeService = new BackupCodeService($this->passwordHasher);
        $user3 = $this->createUser(username: 'index_backupcode', email: 'index_backupcode@example.com', authTfEnabled: true, authTfType: 'google', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $backupCodeService->generate($user3);
        $this->currentUser->login($user3);
        $html = (string) $this->createController($backupCodeService)->index()->getBody();
        self::assertStringContainsString('Regenerate Backup Codes', $html);
        self::assertStringNotContainsString('You have no backup codes remaining', $html);
    }

    public function testRegenerateBackupCodes(): void
    {
        // Not enabled: redirects
        $user1 = $this->createUser(username: 'regen_disabled', email: 'regen_disabled@example.com', authTfEnabled: false, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user1);
        $result = $this->createController()->regenerateBackupCodes();
        $this->assertSame(302, $result->getStatusCode());

        // Invalid email code: shows settings form with error
        $user2 = $this->createUser(username: 'regen_invalid_email', email: 'regen_invalid_email@example.com', authTfEnabled: true, authTfType: 'email', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user2);
        $html = (string) $this->createController()->regenerateBackupCodes(code: 'wrong')->getBody();
        self::assertStringContainsString('To disable two-factor authentication', $html);
        self::assertStringContainsString('Invalid verification code.', $html);
        self::assertStringNotContainsString('Backup Codes', $html);

        // Invalid google code: shows settings without code list
        $user3 = $this->createUser(username: 'regen_invalid_google', email: 'regen_invalid_google@example.com', authTfEnabled: true, authTfType: 'google', authTfKey: null, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user3);
        $html = (string) $this->createController()->regenerateBackupCodes(code: 'wrong')->getBody();
        self::assertStringContainsString('Two factor authentication is not configured.', $html);
        self::assertStringNotContainsString('font-monospace', $html);

        // Valid code: shows new backup codes
        $user4 = $this->createUser(username: 'regen_valid', email: 'regen_valid@example.com', authTfEnabled: true, authTfType: 'email', authTfKey: '123456', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user4);
        $html = (string) $this->createController()->regenerateBackupCodes(code: '123456')->getBody();
        self::assertStringContainsString('Backup Codes', $html);
        self::assertSame(10, substr_count($html, 'font-monospace'));
    }

    public function testRenew(): void
    {
        // Already google type: doesn't reset method
        $user1 = $this->createUser(username: 'renew_google', email: 'renew_google@example.com', authTfEnabled: false, authTfType: 'google', authTfKey: 'secret', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user1);
        $result = $this->createController()->renew();
        $this->assertSame(200, $result->getStatusCode());
        $updated1 = User::findById((int) $user1->getId());
        $this->assertNotNull($updated1);
        $this->assertSame('google', $updated1->getAuthTfType());

        // Email type: generates new secret and switches to google
        $user2 = $this->createUser(username: 'renew_email', email: 'renew_email@example.com', authTfEnabled: false, authTfType: 'email', authTfKey: 'new-secret', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user2);
        $result = $this->createController()->renew();
        $this->assertSame(200, $result->getStatusCode());
        $decoded = json_decode((string) $result->getBody(), true);
        self::assertIsArray($decoded);
        self::assertStringContainsString('<svg', (string) $decoded['qrCodeUri']);
        self::assertNotSame('new-secret', $decoded['secret']);
        $reloaded2 = User::findById((int) $user2->getId());
        self::assertSame($reloaded2?->getAuthTfKey(), $decoded['secret']);
        self::assertSame('google', $reloaded2?->getAuthTfType());

        // Already enabled: returns error
        $user3 = $this->createUser(username: 'renew_enabled', email: 'renew_enabled@example.com', authTfEnabled: true, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user3);
        $result = $this->createController()->renew();
        $this->assertSame(403, $result->getStatusCode());
        $decoded = json_decode((string) $result->getBody(), true);
        self::assertIsArray($decoded);
        self::assertSame('Two-factor authentication is already enabled.', $decoded['error']);
    }

    public function testSendEmailCode(): void
    {
        // Already email type: doesn't reset method
        $user1 = $this->createUser(username: 'send_email_type', email: 'send_email_type@example.com', authTfEnabled: false, authTfType: 'email', passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user1);
        (string) $this->createController()->sendEmailCode()->getBody();
        $this->assertMailSent();
        $updated1 = User::findById((int) $user1->getId());
        $this->assertNotNull($updated1);
        $this->assertSame('email', $updated1->getAuthTfType());

        // Null type: sends code and sets email method
        $user2 = $this->createUser(username: 'send_email_null', email: 'send_email_null@example.com', authTfEnabled: false, authTfType: null, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user2);
        $html = (string) $this->createController()->sendEmailCode()->getBody();
        self::assertStringContainsString('Enter the verification code sent to your email', $html);
        $this->assertMailSent();
        $updated2 = User::findById((int) $user2->getId());
        $this->assertNotNull($updated2);
        $this->assertSame('email', $updated2->getAuthTfType());

        // Already enabled: redirects without sending
        $user3 = $this->createUser(username: 'send_email_enabled', email: 'send_email_enabled@example.com', authTfEnabled: true, passwordHash: $this->passwordHasher->hash('secret'), confirmedAt: time());
        $this->currentUser->login($user3);
        $result = $this->createController()->sendEmailCode();
        $this->assertSame(302, $result->getStatusCode());
        $this->assertNoMailSent();
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
