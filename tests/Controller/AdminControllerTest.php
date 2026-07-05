<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller;

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Http\Method;

final class AdminControllerTest extends TestCase
{
    private ?ConnectionInterface $db = null;
    private ControllerHarness $harness;
    private string $remoteAddr = '198.51.100.42';

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = $this->getDb();
        ConnectionProvider::set($this->db);
        $this->createSchema($this->db);

        $_SERVER['REMOTE_ADDR'] = $this->remoteAddr;
        $this->harness = new ControllerHarness(dirname(__DIR__, 2));
    }

    protected function tearDown(): void
    {
        if ($this->db !== null) {
            $this->dropSchema($this->db);
            ConnectionProvider::clear();
        }

        parent::tearDown();
    }

    public function testIndexAppliesUsernameEmailAndStatusFiltersFromQueryParams(): void
    {
        $this->registerAndConfirmUser('filter-user', 'filter-user@example.test', 'secret123');

        $response = $this->harness->adminController->index(
            $this->harness->request(Method::GET, queryParams: [
                'username' => 'filter-us',
                'email' => 'filter-user@',
                'status' => 'confirmed',
            ]),
        );

        $this->assertSame(200, $response->getStatusCode());
        $html = $this->harness->responseBody($response);
        $this->assertStringContainsString('name="username" value="filter-us"', $html);
        $this->assertStringContainsString('name="email" value="filter-user@"', $html);
        $this->assertStringContainsString('value="confirmed" selected', $html);
        $this->assertStringContainsString('>filter-user<', $html);
    }

    public function testIndexHidesPaginationNavWhenAllUsersFitOnOnePage(): void
    {
        $this->registerAndConfirmUser('lone-user', 'lone-user@example.test', 'secret123');

        $response = $this->harness->adminController->index($this->harness->request(Method::GET));

        $this->assertSame(200, $response->getStatusCode());
        $html = $this->harness->responseBody($response);
        $this->assertStringNotContainsString('pagination', $html);
    }

    public function testIndexPaginatesAcrossExactlyTwoPagesFor51Users(): void
    {
        for ($i = 1; $i <= 51; $i++) {
            $this->registerAndConfirmUser("page-user-{$i}", "page-user-{$i}@example.test", 'secret123');
        }

        $defaultResponse = $this->harness->adminController->index($this->harness->request(Method::GET));
        $defaultHtml = $this->harness->responseBody($defaultResponse);
        $this->assertStringContainsString('class="page-item active"><a href="/voyti/admin?page=1', $defaultHtml);
        $this->assertStringNotContainsString('class="page-item active"><a href="/voyti/admin?page=2', $defaultHtml);
        $this->assertStringNotContainsString('page=3', $defaultHtml);

        $page2Response = $this->harness->adminController->index(
            $this->harness->request(Method::GET, queryParams: ['page' => '2']),
        );
        $page2Html = $this->harness->responseBody($page2Response);
        $this->assertStringContainsString('class="page-item active"><a href="/voyti/admin?page=2', $page2Html);
        $this->assertStringNotContainsString('class="page-item active"><a href="/voyti/admin?page=1', $page2Html);

        $zeroPageResponse = $this->harness->adminController->index(
            $this->harness->request(Method::GET, queryParams: ['page' => '0']),
        );
        $zeroPageHtml = $this->harness->responseBody($zeroPageResponse);
        $this->assertStringContainsString('class="page-item active"><a href="/voyti/admin?page=1', $zeroPageHtml);
    }

    public function testInfoShowsSessionsLink(): void
    {
        $user = $this->registerAndConfirmUser('sess-info', 'sess-info@example.test', 'secret123');
        $userId = (int) $user->getId();

        $response = $this->harness->adminController->info($userId);

        $this->assertSame(200, $response->getStatusCode());
        $html = $this->harness->responseBody($response);
        $this->assertStringContainsString('>Sessions<', $html);
        $this->assertStringContainsString("voyti/admin-session-history/{$userId}", $html);
    }

    public function testSwitchIdentityFailureMessageIsPassedAsViewTitle(): void
    {
        $target = $this->registerAndConfirmUser('blocked-target', 'blocked-target@example.test', 'secret123');
        $target->setBlockedAt(time());
        $target->save();

        $response = $this->harness->adminController->switchIdentity((int) $target->getId());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString(
            'Cannot switch to a blocked user',
            $this->harness->responseBody($response),
        );
    }

    public function testUpdateChangesUsernameEmailAndPassword(): void
    {
        $user = $this->registerAndConfirmUser('origname', 'orig@example.test', 'secret123');
        $originalHash = $user->getPasswordHash();
        $originalPasswordChangedAt = $user->getPasswordChangedAt();
        $originalUpdatedAt = $user->getUpdatedAt();

        // Ensure clock progresses so updated_at / password_changed_at differ.
        sleep(1);

        $response = $this->harness->adminController->update(
            $this->harness->request(
                Method::POST,
                [
                    'user' => [
                        'username' => 'newname',
                        'email' => 'newemail@example.test',
                        'password' => 'newsecret456',
                    ],
                    'assignedItems' => [],
                ],
            ),
            (int) $user->getId(),
        );
        $this->assertSame(302, $response->getStatusCode());

        $updatedUser = $this->harness->users->findById((int) $user->getId());
        $this->assertSame('newname', $updatedUser->getUsername());
        $this->assertSame('newemail@example.test', $updatedUser->getEmail());
        $this->assertNotSame($originalHash, $updatedUser->getPasswordHash());
        $this->assertGreaterThan((int) $originalPasswordChangedAt, $updatedUser->getPasswordChangedAt());
        $this->assertGreaterThan((int) $originalUpdatedAt, $updatedUser->getUpdatedAt());
    }

    public function testUpdateNonArrayUserDataKeepsExistingUsernameAndEmail(): void
    {
        $user = $this->registerAndConfirmUser('nonarray', 'nonarray@example.test', 'secret123');
        $originalUsername = $user->getUsername();
        $originalEmail = $user->getEmail();

        $response = $this->harness->adminController->update(
            $this->harness->request(
                Method::POST,
                [
                    'user' => 'not-an-array',
                ],
            ),
            (int) $user->getId(),
        );
        $this->assertSame(302, $response->getStatusCode());

        $updatedUser = $this->harness->users->findById((int) $user->getId());
        $this->assertSame($originalUsername, $updatedUser->getUsername());
        $this->assertSame($originalEmail, $updatedUser->getEmail());
    }

    public function testUpdateViewChecksAssignedRbacItemCheckboxes(): void
    {
        $this->harness->seedRbacRole('manager');
        $this->harness->seedRbacRole('editor');
        $user = $this->registerAndConfirmUser('walt', 'walt@example.test', 'secret123');

        $assignResponse = $this->harness->adminController->update(
            $this->harness->request(
                Method::POST,
                [
                    'user' => [
                        'username' => $user->getUsername(),
                        'email' => $user->getEmail(),
                        'password' => '',
                    ],
                    'assignedItems' => ['manager'],
                ],
            ),
            (int) $user->getId(),
        );
        $this->assertSame(302, $assignResponse->getStatusCode());

        $viewResponse = $this->harness->adminController->update(
            $this->harness->request(Method::GET),
            (int) $user->getId(),
        );
        $html = $this->harness->responseBody($viewResponse);

        $this->assertMatchesRegularExpression('/name="assignedItems\[\]" value="manager"[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="assignedItems\[\]" value="editor"[^>]*checked/', $html);
    }

    public function testUpdateWithEmptyPasswordDoesNotChangePasswordHash(): void
    {
        $user = $this->registerAndConfirmUser('keeppass', 'keeppass@example.test', 'secret123');
        $originalHash = $user->getPasswordHash();
        $originalPasswordChangedAt = $user->getPasswordChangedAt();

        $response = $this->harness->adminController->update(
            $this->harness->request(
                Method::POST,
                [
                    'user' => [
                        'username' => $user->getUsername(),
                        'email' => $user->getEmail(),
                        'password' => '',
                    ],
                    'assignedItems' => [],
                ],
            ),
            (int) $user->getId(),
        );
        $this->assertSame(302, $response->getStatusCode());

        $updatedUser = $this->harness->users->findById((int) $user->getId());
        $this->assertSame($originalHash, $updatedUser->getPasswordHash());
        $this->assertSame($originalPasswordChangedAt, $updatedUser->getPasswordChangedAt());
    }

    private function createSchema(ConnectionInterface $db): void
    {
        $db->createCommand('CREATE TABLE IF NOT EXISTS {{%user}} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            auth_key VARCHAR(32) NOT NULL,
            auth_tf_enabled INTEGER NOT NULL DEFAULT 0,
            auth_tf_key VARCHAR(64),
            auth_tf_type VARCHAR(20),
            blocked_at INTEGER,
            confirmed_at INTEGER,
            created_at INTEGER NOT NULL,
            flags INTEGER NOT NULL DEFAULT 0,
            gdpr_consent INTEGER NOT NULL DEFAULT 0,
            gdpr_consent_date INTEGER,
            gdpr_deleted INTEGER NOT NULL DEFAULT 0,
            last_login_at INTEGER,
            last_login_ip VARCHAR(45),
            password_changed_at INTEGER,
            registration_ip VARCHAR(45),
            unconfirmed_email VARCHAR(255),
            updated_at INTEGER NOT NULL
        )')->execute();
        $db->createCommand('CREATE TABLE IF NOT EXISTS {{%user_profile}} (
            user_id INTEGER NOT NULL PRIMARY KEY,
            bio TEXT,
            gravatar_email VARCHAR(255),
            location VARCHAR(255),
            name VARCHAR(255),
            public_email VARCHAR(255),
            timezone VARCHAR(40),
            website VARCHAR(255)
        )')->execute();
        $db->createCommand('CREATE TABLE IF NOT EXISTS {{%user_social_account}} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            provider VARCHAR(255) NOT NULL,
            client_id VARCHAR(255) NOT NULL,
            code VARCHAR(32),
            email VARCHAR(255),
            username VARCHAR(255),
            data TEXT,
            created_at INTEGER NOT NULL
        )')->execute();
        $db->createCommand('CREATE TABLE IF NOT EXISTS {{%user_token}} (
            user_id INTEGER NOT NULL,
            code VARCHAR(32) NOT NULL,
            type INTEGER NOT NULL,
            created_at INTEGER NOT NULL,
            PRIMARY KEY (user_id, code, type)
        )')->execute();
        $db->createCommand('CREATE TABLE IF NOT EXISTS {{%user_session_history}} (
            user_id INTEGER NOT NULL,
            session_id VARCHAR(255) NOT NULL,
            user_agent TEXT,
            ip VARCHAR(45) NOT NULL,
            created_at INTEGER NOT NULL,
            updated_at INTEGER NOT NULL,
            PRIMARY KEY (user_id, session_id)
        )')->execute();
    }

    private function dropSchema(ConnectionInterface $db): void
    {
        $db->createCommand('DROP TABLE IF EXISTS {{%user_session_history}}')->execute();
        $db->createCommand('DROP TABLE IF EXISTS {{%user_token}}')->execute();
        $db->createCommand('DROP TABLE IF EXISTS {{%user_social_account}}')->execute();
        $db->createCommand('DROP TABLE IF EXISTS {{%user_profile}}')->execute();
        $db->createCommand('DROP TABLE IF EXISTS {{%user}}')->execute();
    }

    private function registerAndConfirmUser(string $username, string $email, string $password): User
    {
        $registerResponse = $this->harness->registrationController->register(
            $this->harness->request(
                Method::POST,
                $this->harness->formPayload(
                    new \YiiRocks\Voyti\Form\Auth\RegistrationForm($this->harness->moduleConfig, $this->harness->translator),
                    [
                        'username' => $username,
                        'email' => $email,
                        'password' => $password,
                        'gdprConsent' => true,
                    ],
                ),
            ),
        );
        $this->assertSame(200, $registerResponse->getStatusCode());

        $user = $this->harness->users->findByEmail($email);

        $token = $this->harness->userTokens->findByUserId((int) $user->getId())[0] ?? null;

        $confirmResponse = $this->harness->registrationController->confirm(
            $this->harness->request(Method::GET),
            (int) $user->getId(),
            $token->getCode(),
        );
        $this->assertSame(200, $confirmResponse->getStatusCode());

        $confirmedUser = $this->harness->users->findById((int) $user->getId());
        $this->assertTrue($confirmedUser->isConfirmed());

        return $confirmedUser;
    }
}
