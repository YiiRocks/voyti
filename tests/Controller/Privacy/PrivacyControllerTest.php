<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Privacy;

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

    public function testAnonymize(): void
    {
        // GET: shows form
        $controller = $this->createController(VoytiConfigFactory::create(enableGdprCompliance: true));
        $html = (string) $controller->anonymize(new ServerRequest('GET', '/'))->getBody();
        self::assertStringContainsString('Anonymize my account', $html);

        // POST with valid password: anonymizes user, terminates sessions, dispatches event
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
        $this->assertSame('GDPR' . $userId, $updated->getUsername());
        $this->assertSame('GDPR' . $userId . '@example.com', $updated->getEmail(), 'email should be anonymized with prefix and @example.com domain');
        $this->assertNotSame($originalAuthKey, $updated->getAuthKey(), 'authKey should be refreshed during anonymization');
        $this->assertNotEmpty($updated->getAuthKey(), 'authKey should not be empty');
        $this->assertTrue(UserSessions::findByUserIdAndSessionId($userId, 'anon-sess')?->isRevoked());
        self::assertStringContainsString('Your personal information has been removed', $html);
        $event = $this->eventDispatcher->getEvent(GdprEvent::class);
        $this->assertNotNull($event);
        $this->assertTrue($event->getUser()->isAnonymized());
    }

    public function testDelete(): void
    {
        // GET: shows form
        $controller = $this->createController(VoytiConfigFactory::create(allowAccountDelete: true));
        $html = (string) $controller->delete(new ServerRequest('GET', '/'))->getBody();
        self::assertStringContainsString('Delete my account', $html);

        // POST with invalid password: shows form, account intact
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['delete-account' => ['password' => 'wrongpassword', 'consent' => '1']]);
        $user2 = $this->createUser(username: 'del_invalid', email: 'del_invalid@example.com', passwordHash: $this->passwordHasher->hash('correctpassword'), confirmedAt: time());
        $this->currentUser->login($user2);
        $html = (string) $controller->delete($request)->getBody();
        self::assertStringContainsString('Delete my account', $html);
        $this->assertNotNull(User::findById((int) $user2->getId()));

        // POST with valid password: deletes user, terminates sessions, dispatches events
        $password = 'mypassword';
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['delete-account' => ['password' => $password, 'consent' => '1']]);
        $user3 = $this->createUser(username: 'del_valid', email: 'del_valid@example.com', passwordHash: $this->passwordHasher->hash($password), confirmedAt: time());
        $userId3 = (int) $user3->getId();
        $session = new UserSessions();
        $session->setUserId($userId3);
        $session->setSessionId('del-sess');
        $session->setIp('203.0.113.9');
        $session->setCreatedAt(time());
        $session->setUpdatedAt(time());
        $session->save();
        $this->currentUser->login($user3);
        $html = (string) $controller->delete($request)->getBody();
        $this->assertNull(User::findById($userId3));
        $this->assertTrue($this->eventDispatcher->hasEvent(SessionEvent::class));
        self::assertStringContainsString('Your account has been deleted', $html);
        $event = $this->eventDispatcher->getEvent(UserEvent::class);
        $this->assertNotNull($event);
        $this->assertSame(UserEvent::DELETE, $event->getType());
    }

    public function testExport(): void
    {
        // Basic export: returns data with correct headers
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

        // Includes sessions and social accounts
        $controller = $this->createController(VoytiConfigFactory::create(
            enableGdprCompliance: true,
            gdprExportProperties: ['userSessions', 'userSocialAccount'],
        ));
        $user2 = $this->createUser(username: 'exp_sessions', email: 'exp_sessions@example.com', confirmedAt: time());
        $userId2 = (int) $user2->getId();
        $this->currentUser->login($user2);
        $sessionEntry = new UserSessions();
        $sessionEntry->setUserId($userId2);
        $sessionEntry->setSessionId('session-1');
        $sessionEntry->setIp('203.0.113.5');
        $sessionEntry->setUserAgent('TestAgent/1.0');
        $sessionEntry->setCreatedAt(1000);
        $sessionEntry->setUpdatedAt(2000);
        $sessionEntry->save();
        $socialAccount = $this->createSocialAccount($userId2, 'github', 'octocat');
        $socialAccount->setEmail('octocat@example.com');
        $socialAccount->setCreatedAt(3000);
        $socialAccount->setData(json_encode(['name' => 'The Octocat'], JSON_THROW_ON_ERROR));
        $socialAccount->save();
        $json = (string) $controller->export()->getBody();
        $data = json_decode($json, true);
        $this->assertNotNull($data['userSessions']);
        $this->assertNotNull($data['userSocialAccount']);

        // Includes profile fields
        $controller = $this->createController(VoytiConfigFactory::create(
            enableGdprCompliance: true,
            gdprExportProperties: [
                'userProfile.public_email',
                'userProfile.name',
                'userProfile.gravatar_email',
                'userProfile.location',
                'userProfile.website',
                'userProfile.bio',
            ],
        ));
        $user3 = $this->createUser(username: 'exp_profile', email: 'exp_profile@example.com', confirmedAt: time());
        $this->currentUser->login($user3);
        $profile = new UserProfile();
        $profile->setUserId((int) $user3->getId());
        $profile->setPublicEmail('public@example.com');
        $profile->setName('Jane Doe');
        $profile->setGravatarEmail('gravatar@example.com');
        $profile->setLocation('Berlin');
        $profile->setWebsite('https://example.com');
        $profile->setBio('Hello café');
        $profile->save();
        $json = (string) $controller->export()->getBody();
        $data = json_decode($json, true);
        $this->assertSame('public@example.com', $data['userProfile.public_email']);
        $this->assertSame('Jane Doe', $data['userProfile.name']);
        $this->assertSame('gravatar@example.com', $data['userProfile.gravatar_email']);
        $this->assertSame('Berlin', $data['userProfile.location']);

        // Omits null values and unknown properties
        $controller = $this->createController(VoytiConfigFactory::create(
            enableGdprCompliance: true,
            gdprExportProperties: ['email', 'userProfile.birthday', 'totally.unknown'],
        ));
        $user4 = $this->createUser(username: 'exp_null', email: 'exp_null@example.com', confirmedAt: time());
        $this->currentUser->login($user4);
        $json = (string) $controller->export()->getBody();
        $this->assertSame(['email' => 'exp_null@example.com'], json_decode($json, true));
    }

    public function testGdprConsent(): void
    {
        // GET not consented: shows form
        $controller = $this->createController(VoytiConfigFactory::create(enableGdprCompliance: true));
        $user1 = $this->createUser(username: 'gdpr_nocon', email: 'gdpr_nocon@example.com', gdprConsent: false, confirmedAt: time());
        $this->currentUser->login($user1);
        $html = (string) $controller->gdprConsent(new ServerRequest('GET', '/'))->getBody();
        self::assertStringContainsString('GDPR Consent', $html);
        self::assertStringNotContainsString('already given consent', $html);

        // GET already consented: shows consent date
        $user2 = $this->createUser(username: 'gdpr_con', email: 'gdpr_con@example.com', gdprConsent: true, gdprConsentDate: 1700000000, confirmedAt: time());
        $this->currentUser->login($user2);
        $profile = new UserProfile();
        $profile->setUserId((int) $user2->getId());
        $profile->setTimezone('America/New_York');
        $profile->save();
        $html = (string) $controller->gdprConsent(new ServerRequest('GET', '/'))->getBody();
        self::assertStringContainsString(
            TimezoneHelper::formatLocalized(1700000000, $this->createTranslator()->getLocale(), 'America/New_York'),
            $html,
        );

        // GET consented but without a consent date: locked with no date to display
        $userNoDate = $this->createUser(username: 'gdpr_nodate', email: 'gdpr_nodate@example.com', gdprConsent: true, confirmedAt: time());
        $this->currentUser->login($userNoDate);
        $html = (string) $controller->gdprConsent(new ServerRequest('GET', '/'))->getBody();
        self::assertStringContainsString('already given consent on . This cannot be undone.', $html);

        // POST not consented: saves consent and redirects
        $controller = $this->createController(VoytiConfigFactory::create(enableGdprCompliance: true));
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['gdpr-consent' => ['consent' => '1']]);
        $user3 = $this->createUser(username: 'gdpr_post', email: 'gdpr_post@example.com', gdprConsent: false, confirmedAt: time());
        $this->currentUser->login($user3);
        $result = $controller->gdprConsent($request);
        $this->assertSame(302, $result->getStatusCode());
        $updated3 = User::findById((int) $user3->getId());
        $this->assertNotNull($updated3);
        $this->assertTrue($updated3->isGdprConsent());
        $this->assertNotNull($updated3->getGdprConsentDate());

        // POST already consented: no-op
        $controller = $this->createController(VoytiConfigFactory::create(enableGdprCompliance: true));
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['gdpr-consent' => ['consent' => '1']]);
        $user4 = $this->createUser(username: 'gdpr_noop', email: 'gdpr_noop@example.com', gdprConsent: true, confirmedAt: time());
        $consentDate = $user4->getGdprConsentDate();
        $this->currentUser->login($user4);
        $result = $controller->gdprConsent($request);
        $this->assertSame(302, $result->getStatusCode());
        $updated4 = User::findById((int) $user4->getId());
        $this->assertNotNull($updated4);
        $this->assertSame($consentDate, $updated4->getGdprConsentDate());
    }

    public function testIndexShowsView(): void
    {
        // GDPR compliance and account deletion enabled: all action links render with their URLs.
        $html = (string) $this->createController(VoytiConfigFactory::create(
            enableGdprCompliance: true,
            allowAccountDelete: true,
        ))->index()->getBody();

        self::assertStringContainsString('Privacy', $html);
        self::assertStringContainsString('voyti/user-privacy-gdpr-consent', $html);
        self::assertStringContainsString('voyti/user-privacy-export', $html);
        self::assertStringContainsString('voyti/user-privacy-anonymize', $html);
        self::assertStringContainsString('voyti/user-privacy-delete', $html);

        // Both features disabled: no action links render.
        $html = (string) $this->createController(VoytiConfigFactory::create(
            enableGdprCompliance: false,
            allowAccountDelete: false,
        ))->index()->getBody();
        self::assertStringContainsString('Privacy', $html);
        self::assertStringNotContainsString('user-privacy-gdpr-consent', $html);
        self::assertStringNotContainsString('user-privacy-export', $html);
        self::assertStringNotContainsString('user-privacy-anonymize', $html);
        self::assertStringNotContainsString('user-privacy-delete', $html);
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
