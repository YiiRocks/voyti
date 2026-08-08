<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Privacy;

use DateTimeImmutable;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Controller\Privacy\PrivacyController;
use YiiRocks\Voyti\Event\Gdpr\GdprEvent;
use YiiRocks\Voyti\Event\Session\SessionEvent;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\Model\UserSocialAccount;
use YiiRocks\Voyti\tests\Support\CurrentUserTrait;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;
use YiiRocks\Voyti\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\ValidatorMockTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Validator\ValidatorInterface;

#[AllowMockObjectsWithoutExpectations]
final class PrivacyControllerTest extends DatabaseTestCase
{
    use CurrentUserTrait;
    use TestContainerTrait;
    use UserFactoryTrait;
    use ValidatorMockTrait;

    private CurrentUser $currentUser;
    private EventCaptureDispatcher $eventDispatcher;
    private FlashInterface&MockObject $flash;
    private PasswordHasher $passwordHasher;
    private ValidatorInterface&MockObject $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->currentUser = $this->createCurrentUser();
        $this->flash = $this->createMock(FlashInterface::class);
        $this->passwordHasher = TestPasswordHasherFactory::create();
        $this->eventDispatcher = new EventCaptureDispatcher();
        $this->validator = $this->mockValidValidator();
    }

    public function testAnonymizeGetShowsForm(): void
    {
        $controller = $this->createController(VoytiConfigFactory::create(enableGdprCompliance: true));

        $html = (string) $controller->anonymize(new ServerRequest('GET', '/'))->getBody();

        self::assertStringContainsString('Anonymize my account', $html);
    }

    public function testAnonymizePostWithValidPasswordAnonymizesUser(): void
    {
        $controller = $this->createController(VoytiConfigFactory::create(enableGdprCompliance: true));

        $password = 'mypassword';
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['anonymize' => ['password' => $password, 'consent' => '1']]);

        $user = $this->createUser(passwordHash: $this->passwordHasher->hash($password), confirmedAt: time());
        $userId = (int) $user->getId();
        $originalAuthKey = $user->getAuthKey();
        $session = new UserSessions();
        $session->setUserId($userId);
        $session->setSessionId('anon-sess');
        $session->setIp('203.0.113.9');
        $session->setCreatedAt(time());
        $session->setUpdatedAt(time());
        $session->save();
        $this->currentUser->login($user);

        $html = (string) $controller->anonymize($request)->getBody();

        $updated = User::findById($userId);
        $this->assertNotNull($updated);
        $this->assertTrue($updated->isAnonymized());
        $this->assertTrue($updated->isBlocked());
        // Identifying fields are overwritten with the "GDPR<id>" prefix and the auth key is rotated.
        $this->assertSame('GDPR' . $userId, $updated->getUsername());
        $this->assertSame('GDPR' . $userId . '@example.com', $updated->getEmail());
        $this->assertNotSame($originalAuthKey, $updated->getAuthKey());
        // Active sessions are terminated and the confirmation message is shown.
        $this->assertTrue(UserSessions::findByUserIdAndSessionId($userId, 'anon-sess')?->isRevoked());
        self::assertStringContainsString('Your personal information has been removed', $html);
        $event = $this->eventDispatcher->getEvent(GdprEvent::class);
        $this->assertNotNull($event);
        $this->assertTrue($event->getUser()->isAnonymized());
    }

    public function testDeleteGetShowsForm(): void
    {
        $controller = $this->createController(VoytiConfigFactory::create(allowAccountDelete: true));

        $html = (string) $controller->delete(new ServerRequest('GET', '/'))->getBody();

        self::assertStringContainsString('Delete my account', $html);
    }

    public function testDeletePostWithInvalidPasswordShowsForm(): void
    {
        $controller = $this->createController(VoytiConfigFactory::create(allowAccountDelete: true));

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['delete-account' => ['password' => 'wrongpassword', 'consent' => '1']]);

        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('correctpassword'), confirmedAt: time());
        $this->currentUser->login($user);

        $html = (string) $controller->delete($request)->getBody();

        // A wrong password re-renders the delete form and leaves the account intact.
        self::assertStringContainsString('Delete my account', $html);
        $this->assertNotNull(User::findById((int) $user->getId()));
    }

    public function testDeletePostWithValidPasswordDeletesUser(): void
    {
        $controller = $this->createController(VoytiConfigFactory::create(allowAccountDelete: true));

        $password = 'mypassword';
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['delete-account' => ['password' => $password, 'consent' => '1']]);

        $user = $this->createUser(passwordHash: $this->passwordHasher->hash($password), confirmedAt: time());
        $userId = (int) $user->getId();
        $session = new UserSessions();
        $session->setUserId($userId);
        $session->setSessionId('del-sess');
        $session->setIp('203.0.113.9');
        $session->setCreatedAt(time());
        $session->setUpdatedAt(time());
        $session->save();
        $this->currentUser->login($user);

        $html = (string) $controller->delete($request)->getBody();

        $this->assertNull(User::findById($userId));
        // Sessions are terminated (SessionEvent dispatched) and the deletion message is shown.
        $this->assertTrue($this->eventDispatcher->hasEvent(SessionEvent::class));
        self::assertStringContainsString('Your account has been deleted', $html);
        $event = $this->eventDispatcher->getEvent(UserEvent::class);
        $this->assertNotNull($event);
        $this->assertSame(UserEvent::DELETE, $event->getType());
    }

    public function testExportIncludesSessionsAndSocialAccounts(): void
    {
        $controller = $this->createController(VoytiConfigFactory::create(
            enableGdprCompliance: true,
            gdprExportProperties: ['userSessions', 'userSocialAccount'],
        ));

        $user = $this->createUser(confirmedAt: time());
        $userId = (int) $user->getId();
        $this->currentUser->login($user);

        $sessionEntry = new UserSessions();
        $sessionEntry->setUserId($userId);
        $sessionEntry->setSessionId('session-1');
        $sessionEntry->setIp('203.0.113.5');
        $sessionEntry->setUserAgent('TestAgent/1.0');
        $sessionEntry->setCreatedAt(1000);
        $sessionEntry->setUpdatedAt(2000);
        $sessionEntry->save();

        $socialAccount = $this->createSocialAccount($userId, 'github', 'octocat');
        $socialAccount->setEmail('octocat@example.com');
        $socialAccount->setCreatedAt(3000);
        $socialAccount->setData(json_encode(['name' => 'The Octocat', 'avatar_url' => 'https://example.com/avatar.png'], JSON_THROW_ON_ERROR));
        $socialAccount->save();

        $expected = [
            'userSessions' => [
                ['ip' => '203.0.113.5', 'user_agent' => 'TestAgent/1.0', 'created_at' => 1000, 'updated_at' => 2000],
            ],
            'userSocialAccount' => [
                [
                    'provider' => 'github',
                    'username' => 'octocat',
                    'email' => 'octocat@example.com',
                    'created_at' => 3000,
                    'data' => ['name' => 'The Octocat', 'avatar_url' => 'https://example.com/avatar.png'],
                ],
            ],
        ];

        $json = (string) $controller->export()->getBody();

        $this->assertSame($expected, json_decode($json, true));
    }

    public function testExportIncludesUserProfileFields(): void
    {
        $controller = $this->createController(VoytiConfigFactory::create(
            enableGdprCompliance: true,
            gdprExportProperties: [
                'userProfile.public_email',
                'userProfile.name',
                'userProfile.gravatar_email',
                'userProfile.location',
                'userProfile.website',
                'userProfile.bio',
                'userProfile.birthday',
            ],
        ));

        $user = $this->createUser(confirmedAt: time());
        $this->currentUser->login($user);

        $profile = new UserProfile();
        $profile->setUserId((int) $user->getId());
        $profile->setPublicEmail('public@example.com');
        $profile->setName('Jane Doe');
        $profile->setGravatarEmail('gravatar@example.com');
        $profile->setLocation('Berlin');
        $profile->setWebsite('https://example.com');
        $profile->setBio('Hello café');
        $profile->setBirthday(new DateTimeImmutable('1990-05-15'));
        $profile->save();

        $expected = [
            'userProfile.public_email' => 'public@example.com',
            'userProfile.name' => 'Jane Doe',
            'userProfile.gravatar_email' => 'gravatar@example.com',
            'userProfile.location' => 'Berlin',
            'userProfile.website' => 'https://example.com',
            'userProfile.bio' => 'Hello café',
            'userProfile.birthday' => '1990-05-15',
        ];

        $json = (string) $controller->export()->getBody();

        $this->assertSame($expected, json_decode($json, true));
        // The raw JSON is pretty-printed with unescaped slashes and unicode.
        self::assertStringContainsString("\n", $json);
        self::assertStringContainsString('https://example.com', $json);
        self::assertStringContainsString('Hello café', $json);
    }

    public function testExportOmitsNullBirthdayWhenProfileExists(): void
    {
        $controller = $this->createController(VoytiConfigFactory::create(
            enableGdprCompliance: true,
            gdprExportProperties: ['email', 'userProfile.birthday'],
        ));

        $user = $this->createUser(email: 'nobday@example.com', confirmedAt: time());
        $this->currentUser->login($user);
        $profile = new UserProfile();
        $profile->setUserId((int) $user->getId());
        $profile->setName('Has Profile No Birthday');
        $profile->save();

        $json = (string) $controller->export()->getBody();

        // The profile exists but has no birthday, so the null-safe birthday format yields null (dropped).
        $this->assertSame(['email' => 'nobday@example.com'], json_decode($json, true));
    }

    public function testExportOmitsNullValuesAndUnknownProperties(): void
    {
        // The user has no profile, and an unknown property is requested: profile fields resolve to
        // null (null-safe), the unknown property hits the match default (null), and both are dropped.
        $controller = $this->createController(VoytiConfigFactory::create(
            enableGdprCompliance: true,
            gdprExportProperties: [
                'email',
                'userProfile.public_email',
                'userProfile.name',
                'userProfile.gravatar_email',
                'userProfile.location',
                'userProfile.website',
                'userProfile.bio',
                'userProfile.birthday',
                'totally.unknown',
            ],
        ));

        $user = $this->createUser(email: 'lone@example.com', confirmedAt: time());
        $this->currentUser->login($user);

        $json = (string) $controller->export()->getBody();

        // Every profile field is null (no profile) and the unknown property hits the match default;
        // all are dropped, leaving only the email.
        $this->assertSame(['email' => 'lone@example.com'], json_decode($json, true));
    }

    public function testExportReturnsData(): void
    {
        $controller = $this->createController(VoytiConfigFactory::create(
            enableGdprCompliance: true,
            gdprExportProperties: ['email', 'username'],
        ));

        $user = $this->createUser(confirmedAt: time());
        $this->currentUser->login($user);

        $result = $controller->export();

        $this->assertSame(200, $result->getStatusCode());
        $this->assertStringContainsString('application/json', $result->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('user-data-export.json', $result->getHeaderLine('Content-Disposition'));
        $this->assertSame(
            ['email' => 'test@example.com', 'username' => 'testuser'],
            json_decode((string) $result->getBody(), true),
        );
    }

    public function testGdprConsentGetShowsConsentDateWhenAlreadyConsented(): void
    {
        $controller = $this->createController(VoytiConfigFactory::create(enableGdprCompliance: true));

        $user = $this->createUser(gdprConsent: true, gdprConsentDate: 1700000000, confirmedAt: time());
        $this->currentUser->login($user);

        $profile = new UserProfile();
        $profile->setUserId((int) $user->getId());
        $profile->setTimezone('America/New_York');
        $profile->save();

        $html = (string) $controller->gdprConsent(new ServerRequest('GET', '/'))->getBody();

        // The locked notice shows the consent date formatted in the viewer's timezone.
        self::assertStringContainsString(
            TimezoneHelper::formatLocalized(1700000000, $this->createTranslator()->getLocale(), 'America/New_York'),
            $html,
        );
    }

    public function testGdprConsentGetShowsForm(): void
    {
        $controller = $this->createController(VoytiConfigFactory::create(enableGdprCompliance: true));

        $user = $this->createUser(gdprConsent: false, confirmedAt: time());
        $this->currentUser->login($user);

        $html = (string) $controller->gdprConsent(new ServerRequest('GET', '/'))->getBody();

        // Not yet consented: the form is editable, without the "already given consent" locked notice.
        self::assertStringContainsString('GDPR Consent', $html);
        self::assertStringNotContainsString('already given consent', $html);
    }

    public function testGdprConsentPostAlreadyConsentedResubmitIsNoop(): void
    {
        $controller = $this->createController(VoytiConfigFactory::create(enableGdprCompliance: true));
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['gdpr-consent' => ['consent' => '1']]);

        $user = $this->createUser(gdprConsent: true, confirmedAt: time());
        $consentDate = $user->getGdprConsentDate();
        $this->currentUser->login($user);

        $result = $controller->gdprConsent($request);

        $this->assertSame(302, $result->getStatusCode());
        $this->assertStringContainsString('gdpr-consent', $result->getHeaderLine('Location'));
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertSame($consentDate, $updated->getGdprConsentDate());
    }

    public function testGdprConsentPostSavesAndRedirects(): void
    {
        $controller = $this->createController(VoytiConfigFactory::create(enableGdprCompliance: true));
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['gdpr-consent' => ['consent' => '1']]);

        $user = $this->createUser(gdprConsent: false, confirmedAt: time());
        $this->currentUser->login($user);

        $result = $controller->gdprConsent($request);

        $this->assertSame(302, $result->getStatusCode());
        $this->assertStringContainsString('gdpr-consent', $result->getHeaderLine('Location'));
        $updated = User::findById((int) $user->getId());
        $this->assertNotNull($updated);
        $this->assertTrue($updated->isGdprConsent());
        $this->assertNotNull($updated->getGdprConsentDate());
    }

    public function testIndexShowsView(): void
    {
        $html = (string) $this->createController()->index()->getBody();

        self::assertStringContainsString('Privacy', $html);
    }

    private function createController(?VoytiConfig $config = null): PrivacyController
    {
        $overrides = [
            CurrentUser::class => $this->currentUser,
            EventDispatcherInterface::class => $this->eventDispatcher,
            FlashInterface::class => $this->flash,
            ValidatorInterface::class => $this->validator,
        ];

        if ($config !== null) {
            $overrides[VoytiConfig::class] = $config;
        }

        return $this->getTestContainer($overrides)->get(PrivacyController::class);
    }

    private function createSocialAccount(int $userId, string $provider = 'github', string $username = 'octocat'): UserSocialAccount
    {
        $account = new UserSocialAccount();
        $account->setUserId($userId);
        $account->setProvider($provider);
        $account->setClientId('client123');
        $account->setUsername($username);
        $account->setCreatedAt(time());
        $account->save();

        return $account;
    }
}
