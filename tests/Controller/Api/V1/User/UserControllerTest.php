<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Api\V1\User;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\Controller\Api\V1\User\UserController;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserPasswordHistory;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Service\Password\PasswordGeneratorInterface;
use YiiRocks\Voyti\Service\Password\PasswordHistoryService;
use YiiRocks\Voyti\Service\Password\RandomPasswordGenerator;
use YiiRocks\Voyti\Service\User\UserCreationHelper;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;
use YiiRocks\Voyti\tests\Support\MailCapture;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\DataResponse\Middleware\JsonDataResponseMiddleware;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactory;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\View;

#[AllowMockObjectsWithoutExpectations]
final class UserControllerTest extends TestCase
{
    use DatabaseSetupTrait;
    use UserFactoryTrait;

    private VoytiConfig $config;
    private PasswordGeneratorInterface&MockObject $passwordGenerator;
    private DataResponseFactoryInterface&MockObject $responseFactory;
    private TranslatorInterface $translator;
    private UserCreationHelper $userCreationHelper;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->config = VoytiConfigFactory::create();
        $this->translator = $this->createTranslator();
        $passwordHasher = TestPasswordHasherFactory::create();
        $passwordHistoryService = new PasswordHistoryService($passwordHasher, $this->config);
        $mailer = new MailCapture();
        $url = $this->createMock(UrlGeneratorInterface::class);
        $mailService = new MailService($mailer, '/tmp', new View(), $this->translator, $url, 'Test');
        $this->userCreationHelper = new UserCreationHelper(
            $mailService,
            new EventCaptureDispatcher(),
            $passwordHasher,
            $this->config,
            $passwordHistoryService,
        );
        $this->responseFactory = $this->createMock(DataResponseFactoryInterface::class);
        $this->passwordGenerator = $this->createMock(PasswordGeneratorInterface::class);
        $this->passwordGenerator->method('generate')->willReturn('fallback-generated-password');
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testCreateEmailAlreadyExists(): void
    {
        $this->createUser('existinguser', 'existing@example.com');

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(['error' => 'Email already exists'], 400)
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->create(email: 'existing@example.com', username: 'newuser', password: 'secret123');

        $this->assertSame($response, $result);
    }

    public function testCreateHandlesRaceLostAfterUniquenessCheckPasses(): void
    {
        $this->createUser('existinguser', 'existing@example.com');

        $passwordHasher = TestPasswordHasherFactory::create();
        $mailer = new MailCapture();
        $url = $this->createMock(UrlGeneratorInterface::class);
        $mailService = new MailService($mailer, '/tmp', new View(), $this->translator, $url, 'Test');
        $userCreationHelper = $this->getMockBuilder(UserCreationHelper::class)
            ->setConstructorArgs([
                $mailService,
                new EventCaptureDispatcher(),
                $passwordHasher,
                $this->config,
                new PasswordHistoryService($passwordHasher, $this->config),
            ])
            ->onlyMethods(['findUniquenessConflict'])
            ->getMock();
        $userCreationHelper->method('findUniquenessConflict')->willReturnOnConsecutiveCalls(null, 'Email already exists');

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(['error' => 'Email already exists'], 400)
            ->willReturn($response);

        $controller = new UserController(
            translator: $this->translator,
            config: $this->config,
            responseFactory: $this->responseFactory,
            passwordGenerator: $this->passwordGenerator,
            passwordHistoryService: new PasswordHistoryService($passwordHasher, $this->config),
            userCreationHelper: $userCreationHelper,
        );

        $result = $controller->create(email: 'existing@example.com', username: 'newuser2', password: 'secret123');

        $this->assertSame($response, $result);
    }

    public function testCreateRecordsPasswordHistory(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);

        $config = VoytiConfigFactory::create(maxPasswordAge: 90);
        $controller = $this->createController($config);
        $controller->create(email: 'history@example.com', username: 'historyuser', password: 'secret123');

        $created = User::findByEmail('history@example.com');
        $this->assertNotNull($created);
        self::assertCount(1, UserPasswordHistory::findByUserId((int) $created->getId()));
    }

    public function testCreateResponseIsJsonFormattableThroughRealPipeline(): void
    {
        $controller = new UserController(
            translator: $this->translator,
            config: $this->config,
            responseFactory: new DataResponseFactory(new Psr17Factory()),
            passwordGenerator: new RandomPasswordGenerator(),
            passwordHistoryService: new PasswordHistoryService(TestPasswordHasherFactory::create(), $this->config),
            userCreationHelper: $this->userCreationHelper,
        );

        $response = $controller->create(email: 'real@example.com', username: 'realuser', password: 'secret123');

        $handler = new readonly class ($response) implements RequestHandlerInterface {
            public function __construct(private ResponseInterface $response) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        $formatted = (new JsonDataResponseMiddleware())->process(
            $this->createMock(ServerRequestInterface::class),
            $handler,
        );

        self::assertSame(201, $formatted->getStatusCode());
        self::assertStringContainsString('application/json', $formatted->getHeaderLine('Content-Type'));

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $formatted->getBody(), true);
        self::assertSame('realuser', $body['username']);
        self::assertSame('real@example.com', $body['email']);
    }

    public function testCreateSuccess(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(self::callback(static function (array $data): bool {
                return $data['username'] === 'newuser'
                    && $data['email'] === 'new@example.com'
                    && $data['message'] !== ''
                    && array_key_exists('id', $data);
            }), 201)
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->create(email: 'new@example.com', username: 'newuser', password: 'secret123');

        $this->assertSame($response, $result);
        $created = User::findByEmail('new@example.com');
        $this->assertNotNull($created);
        $this->assertNotEmpty($created->getAuthKey());
        $this->assertNotNull($created->getConfirmedAt());
        $this->assertGreaterThan(0, $created->getCreatedAt());
        $this->assertGreaterThan(0, $created->getUpdatedAt());
        $this->assertTrue(password_verify('secret123', $created->getPasswordHash()));
    }

    public function testCreateUsernameAlreadyExists(): void
    {
        $this->createUser('existinguser', 'other@example.com');

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(['error' => 'Username already exists'], 400)
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->create(email: 'new@example.com', username: 'existinguser', password: 'secret123');

        $this->assertSame($response, $result);
    }

    public function testCreateWithoutPasswordUsesGeneratedPassword(): void
    {
        $this->passwordGenerator = $this->createMock(PasswordGeneratorInterface::class);
        $this->passwordGenerator->expects($this->once())->method('generate')->with(12)->willReturn('generated-secret');

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);

        $controller = $this->createController();
        $result = $controller->create(email: 'generated@example.com', username: 'generateduser');

        $this->assertSame($response, $result);
        $created = User::findByEmail('generated@example.com');
        $this->assertNotNull($created);
        $this->assertTrue(password_verify('generated-secret', $created->getPasswordHash()));
    }

    public function testDeleteNotFound(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(['error' => 'Not found'], 404)
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->delete(999999);

        $this->assertSame($response, $result);
    }

    public function testDeleteSuccess(): void
    {
        $user = $this->createUser('deleteuser', 'delete@example.com');
        $userId = (int) $user->getId();

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(['message' => 'User deleted'])
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->delete($userId);

        $this->assertSame($response, $result);
        $this->assertNull(User::findById($userId));
    }

    public function testIndexClampsPerPageAboveMaximum(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(self::callback(static fn(array $data): bool => $data['pageSize'] === 100))
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->index(perPage: 500);

        $this->assertSame($response, $result);
    }

    public function testIndexClampsPerPageBelowMinimum(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(self::callback(static fn(array $data): bool => $data['pageSize'] === 1))
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->index(perPage: 0);

        $this->assertSame($response, $result);
    }

    public function testIndexCustomPerPage(): void
    {
        $this->createUser('user0', 'user0@example.com');
        $this->createUser('user1', 'user1@example.com');
        $this->createUser('user2', 'user2@example.com');

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(self::callback(static function (array $data): bool {
                return $data['pageSize'] === 2
                    && $data['totalPages'] === 2
                    && count($data['items']) === 2;
            }))
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->index(perPage: 2);

        $this->assertSame($response, $result);
    }

    public function testIndexDefaultPageWithMultipleUsers(): void
    {
        for ($i = 0; $i < 26; $i++) {
            $this->createUser("user$i", "$i@example.com");
        }

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(self::callback(static function (array $data): bool {
                return $data['currentPage'] === 1
                    && count($data['items']) === 25
                    && $data['totalCount'] === 26
                    && $data['totalPages'] === 2;
            }))
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->index();

        $this->assertSame($response, $result);
    }

    public function testIndexEmptyDatabaseReturnsPageOne(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(self::callback(static function (array $data): bool {
                return $data['items'] === []
                    && $data['totalCount'] === 0
                    && $data['currentPage'] === 1
                    && $data['totalPages'] === 0;
            }))
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->index();

        $this->assertSame($response, $result);
    }

    public function testIndexFiltersByStatus(): void
    {
        $this->createUser('blocked', 'blocked@example.com', blockedAt: time());
        $this->createUser('active', 'active@example.com');

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(self::callback(static function (array $data): bool {
                return count($data['items']) === 1
                    && $data['items'][0]['username'] === 'blocked';
            }))
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->index(status: 'blocked');

        $this->assertSame($response, $result);
    }

    public function testIndexFiltersByUsername(): void
    {
        $this->createUser('alice', 'alice@example.com');
        $this->createUser('bob', 'bob@example.com');

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(self::callback(static function (array $data): bool {
                return count($data['items']) === 1
                    && $data['items'][0]['username'] === 'alice';
            }))
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->index(username: 'alice');

        $this->assertSame($response, $result);
    }

    public function testIndexPageBeyondTotalClampsToLast(): void
    {
        $this->createUser('testuser', 'test@example.com');

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(self::callback(static function (array $data): bool {
                return $data['currentPage'] === 1
                    && count($data['items']) === 1;
            }))
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->index(page: 999);

        $this->assertSame($response, $result);
    }

    public function testIndexPageTwoWithMultiplePages(): void
    {
        for ($i = 0; $i < 26; $i++) {
            $this->createUser("user$i", "$i@example.com");
        }

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(self::callback(static function (array $data): bool {
                return $data['currentPage'] === 2
                    && count($data['items']) === 1
                    && $data['totalCount'] === 26
                    && $data['totalPages'] === 2;
            }))
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->index(page: 2);

        $this->assertSame($response, $result);
    }

    public function testIndexPageZeroClampsToOne(): void
    {
        $this->createUser('testuser', 'test@example.com');

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(self::callback(static function (array $data): bool {
                return $data['currentPage'] === 1
                    && count($data['items']) === 1;
            }))
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->index(page: 0);

        $this->assertSame($response, $result);
    }

    public function testIndexReturnsUsers(): void
    {
        $user = $this->createUser('testuser', 'test@example.com');

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(self::callback(static function (array $data) use ($user): bool {
                return count($data['items']) === 1
                    && $data['items'][0]['id'] === $user->getId()
                    && $data['items'][0]['username'] === 'testuser'
                    && $data['items'][0]['email'] === 'test@example.com'
                    && $data['items'][0]['createdAt'] === $user->getCreatedAt()
                    && $data['items'][0]['confirmedAt'] === $user->getConfirmedAt()
                    && $data['items'][0]['blockedAt'] === $user->getBlockedAt()
                    && $data['totalCount'] === 1
                    && $data['currentPage'] === 1
                    && $data['pageSize'] === 25
                    && $data['totalPages'] === 1;
            }))
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->index();

        $this->assertSame($response, $result);
    }

    public function testUpdateNotFound(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(['error' => 'Not found'], 404)
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->update(id: 999999);

        $this->assertSame($response, $result);
    }

    public function testUpdateSuccess(): void
    {
        $user = $this->createUser('testuser', 'test@example.com');
        $userId = (int) $user->getId();
        $user->setUpdatedAt(1000);
        $user->save();

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(self::callback(static function (array $data) use ($userId): bool {
                return $data['id'] === (string) $userId
                    && $data['username'] === 'updated'
                    && $data['email'] === 'updated@example.com'
                    && $data['message'] === 'User updated';
            }))
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->update(username: 'updated', email: 'updated@example.com', id: $userId);

        $this->assertSame($response, $result);
        $updated = User::findById($userId);
        $this->assertNotNull($updated);
        $this->assertSame('updated', $updated->getUsername());
        $this->assertSame('updated@example.com', $updated->getEmail());
        $this->assertGreaterThan(1000, $updated->getUpdatedAt());
    }

    public function testUpdateWithoutPasswordDoesNotRecordPasswordHistory(): void
    {
        $config = VoytiConfigFactory::create(maxPasswordAge: 90);
        $user = $this->createUser('testuser', 'test@example.com');
        $userId = (int) $user->getId();

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);

        $controller = $this->createController($config);
        $controller->update(username: 'updated', id: $userId);

        self::assertCount(0, UserPasswordHistory::findByUserId($userId));
    }

    public function testUpdateWithPassword(): void
    {
        $user = $this->createUser('testuser', 'test@example.com');
        $userId = (int) $user->getId();
        $originalHash = $user->getPasswordHash();

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(self::callback(static function (array $data) use ($userId): bool {
                return $data['id'] === (string) $userId
                    && $data['username'] === 'updated'
                    && $data['email'] === 'updated@example.com'
                    && $data['message'] === 'User updated';
            }))
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->update(password: 'newpass', username: 'updated', email: 'updated@example.com', id: $userId);

        $this->assertSame($response, $result);
        $updated = User::findById($userId);
        $this->assertNotNull($updated);
        $this->assertNotSame($originalHash, $updated->getPasswordHash());
        $this->assertNotNull($updated->getPasswordChangedAt());
        $this->assertGreaterThan(0, $updated->getUpdatedAt());
    }

    public function testUpdateWithPasswordRecordsPasswordHistory(): void
    {
        $config = VoytiConfigFactory::create(maxPasswordAge: 90);
        $user = $this->createUser('testuser', 'test@example.com');
        $userId = (int) $user->getId();

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);

        $controller = $this->createController($config);
        $controller->update(password: 'newpass', id: $userId);

        self::assertCount(1, UserPasswordHistory::findByUserId($userId));
    }

    public function testUpdateWithPreviouslyUsedPasswordReturnsBadRequest(): void
    {
        $config = VoytiConfigFactory::create(maxPasswordAge: 90);
        $user = $this->createUser('testuser', 'test@example.com');
        $userId = (int) $user->getId();
        $passwordHasher = TestPasswordHasherFactory::create();
        $user->setPasswordHash($passwordHasher->hash('originalpass'));
        $user->save();
        (new PasswordHistoryService($passwordHasher, $config))->record($user);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(['error' => 'This password has been used recently. Please choose a different one.'], 400)
            ->willReturn($response);

        $controller = $this->createController($config);
        $result = $controller->update(password: 'originalpass', id: $userId);

        $this->assertSame($response, $result);
    }

    public function testViewFound(): void
    {
        $user = $this->createUser('testuser', 'test@example.com');
        $userId = (int) $user->getId();

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(self::callback(static function (array $data) use ($user, $userId): bool {
                return $data['id'] === (string) $userId
                    && $data['username'] === 'testuser'
                    && $data['email'] === 'test@example.com'
                    && $data['createdAt'] === $user->getCreatedAt();
            }))
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->view($userId);

        $this->assertSame($response, $result);
    }

    public function testViewNotFound(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(['error' => 'Not found'], 404)
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->view(999999);

        $this->assertSame($response, $result);
    }

    private function createController(?VoytiConfig $config = null): UserController
    {
        $config ??= $this->config;

        return new UserController(
            translator: $this->translator,
            config: $config,
            responseFactory: $this->responseFactory,
            passwordGenerator: $this->passwordGenerator,
            passwordHistoryService: new PasswordHistoryService(TestPasswordHasherFactory::create(), $config),
            userCreationHelper: $this->userCreationHelper,
        );
    }
}
