<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\api\v1\User;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\Controller\api\v1\User\UserController;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserPasswordHistory;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Service\Password\PasswordGeneratorInterface;
use YiiRocks\Voyti\Service\Password\PasswordHistoryService;
use YiiRocks\Voyti\Service\Password\RandomPasswordGenerator;
use YiiRocks\Voyti\Service\User\UserCreationHelper;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;
use YiiRocks\Voyti\tests\Support\MailCapture;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\DataResponse\Middleware\JsonDataResponseMiddleware;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactory;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Translator\TranslatorInterface;

#[AllowMockObjectsWithoutExpectations]
final class UserControllerTest extends TestCase
{
    use DatabaseSetupTrait;

    private ModuleConfig $config;
    private PasswordGeneratorInterface&MockObject $passwordGenerator;
    private DataResponseFactoryInterface&MockObject $responseFactory;
    private TranslatorInterface $translator;
    private UserCreationHelper $userCreationHelper;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->config = new ModuleConfig();
        $this->translator = $this->createTranslator();
        $passwordHasher = new PasswordHasher();
        $passwordHistoryService = new PasswordHistoryService($passwordHasher, $this->config);
        $mailer = new MailCapture();
        $url = $this->createMock(UrlGeneratorInterface::class);
        $mailService = new MailService($mailer, '/tmp', $this->translator, $url, 'Test');
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

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['email' => 'existing@example.com', 'username' => 'newuser', 'password' => 'secret123']);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(['error' => 'Email already exists'], 400)
            ->willReturn($response);

        $result = $controller->create($request);

        $this->assertSame($response, $result);
    }

    public function testCreateRecordsPasswordHistory(): void
    {
        $config = new ModuleConfig(enablePasswordExpiration: true);
        $controller = $this->createController($config);
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['email' => 'history@example.com', 'username' => 'historyuser', 'password' => 'secret123']);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);

        $controller->create($request);

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
            passwordHistoryService: new PasswordHistoryService(new PasswordHasher(), $this->config),
            userCreationHelper: $this->userCreationHelper,
        );

        $request = (new ServerRequest('POST', '/'))
            ->withParsedBody(['email' => 'real@example.com', 'username' => 'realuser', 'password' => 'secret123']);

        $response = $controller->create($request);

        $handler = new class($response) implements RequestHandlerInterface {
            public function __construct(private readonly ResponseInterface $response)
            {
            }

            #[\Override]
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
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['email' => 'new@example.com', 'username' => 'newuser', 'password' => 'secret123']);

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

        $result = $controller->create($request);

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

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['email' => 'new@example.com', 'username' => 'existinguser', 'password' => 'secret123']);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(['error' => 'Username already exists'], 400)
            ->willReturn($response);

        $result = $controller->create($request);

        $this->assertSame($response, $result);
    }

    public function testCreateWithoutPasswordUsesGeneratedPassword(): void
    {
        $this->passwordGenerator = $this->createMock(PasswordGeneratorInterface::class);
        $this->passwordGenerator->expects($this->once())->method('generate')->with(12)->willReturn('generated-secret');

        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['email' => 'generated@example.com', 'username' => 'generateduser']);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);

        $result = $controller->create($request);

        $this->assertSame($response, $result);
        $created = User::findByEmail('generated@example.com');
        $this->assertNotNull($created);
        $this->assertTrue(password_verify('generated-secret', $created->getPasswordHash()));
    }

    public function testDeleteNotFound(): void
    {
        $controller = $this->createController();

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(['error' => 'Not found'], 404)
            ->willReturn($response);

        $result = $controller->delete(999999);

        $this->assertSame($response, $result);
    }

    public function testDeleteSuccess(): void
    {
        $user = $this->createUser('deleteuser', 'delete@example.com');
        $userId = (int) $user->getId();

        $controller = $this->createController();

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(['message' => 'User deleted'])
            ->willReturn($response);

        $result = $controller->delete($userId);

        $this->assertSame($response, $result);
        $this->assertNull(User::findById($userId));
    }

    public function testIndexReturnsUsers(): void
    {
        $user = $this->createUser('testuser', 'test@example.com');

        $controller = $this->createController();

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(self::callback(static function (array $data) use ($user): bool {
                return count($data) === 1
                    && $data[0]['id'] === $user->getId()
                    && $data[0]['username'] === 'testuser'
                    && $data[0]['email'] === 'test@example.com'
                    && $data[0]['createdAt'] === $user->getCreatedAt()
                    && $data[0]['confirmedAt'] === $user->getConfirmedAt()
                    && $data[0]['blockedAt'] === $user->getBlockedAt();
            }))
            ->willReturn($response);

        $result = $controller->index();

        $this->assertSame($response, $result);
    }

    public function testUpdateNotFound(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('PUT', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(['error' => 'Not found'], 404)
            ->willReturn($response);

        $result = $controller->update($request, 999999);

        $this->assertSame($response, $result);
    }

    public function testUpdateSuccess(): void
    {
        $user = $this->createUser('testuser', 'test@example.com');
        $userId = (int) $user->getId();
        $user->setUpdatedAt(1000);
        $user->save();

        $controller = $this->createController();
        $request = (new ServerRequest('PUT', '/'))->withParsedBody(['username' => 'updated', 'email' => 'updated@example.com']);

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

        $result = $controller->update($request, $userId);

        $this->assertSame($response, $result);
        $updated = User::findById($userId);
        $this->assertNotNull($updated);
        $this->assertSame('updated', $updated->getUsername());
        $this->assertSame('updated@example.com', $updated->getEmail());
        $this->assertGreaterThan(1000, $updated->getUpdatedAt());
    }

    public function testUpdateWithNonStringUsernameAndEmailIgnoresThem(): void
    {
        $user = $this->createUser('testuser', 'test@example.com');
        $userId = (int) $user->getId();

        $controller = $this->createController();
        $request = (new ServerRequest('PUT', '/'))->withParsedBody(['username' => ['nested'], 'email' => 12345]);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);

        $result = $controller->update($request, $userId);

        $this->assertSame($response, $result);
        $updated = User::findById($userId);
        $this->assertNotNull($updated);
        $this->assertSame('testuser', $updated->getUsername());
        $this->assertSame('test@example.com', $updated->getEmail());
    }

    public function testUpdateWithoutPasswordDoesNotRecordPasswordHistory(): void
    {
        $config = new ModuleConfig(enablePasswordExpiration: true);
        $user = $this->createUser('testuser', 'test@example.com');
        $userId = (int) $user->getId();

        $controller = $this->createController($config);
        $request = (new ServerRequest('PUT', '/'))->withParsedBody(['username' => 'updated']);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);

        $controller->update($request, $userId);

        self::assertCount(0, UserPasswordHistory::findByUserId($userId));
    }

    public function testUpdateWithPassword(): void
    {
        $user = $this->createUser('testuser', 'test@example.com');
        $userId = (int) $user->getId();
        $originalHash = $user->getPasswordHash();

        $controller = $this->createController();
        $request = (new ServerRequest('PUT', '/'))->withParsedBody(['username' => 'updated', 'email' => 'updated@example.com', 'password' => 'newpass']);

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

        $result = $controller->update($request, $userId);

        $this->assertSame($response, $result);
        $updated = User::findById($userId);
        $this->assertNotNull($updated);
        $this->assertNotSame($originalHash, $updated->getPasswordHash());
        $this->assertNotNull($updated->getPasswordChangedAt());
        $this->assertGreaterThan(0, $updated->getUpdatedAt());
    }

    public function testUpdateWithPasswordRecordsPasswordHistory(): void
    {
        $config = new ModuleConfig(enablePasswordExpiration: true);
        $user = $this->createUser('testuser', 'test@example.com');
        $userId = (int) $user->getId();

        $controller = $this->createController($config);
        $request = (new ServerRequest('PUT', '/'))->withParsedBody(['password' => 'newpass']);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);

        $controller->update($request, $userId);

        self::assertCount(1, UserPasswordHistory::findByUserId($userId));
    }

    public function testUpdateWithPreviouslyUsedPasswordReturnsBadRequest(): void
    {
        $config = new ModuleConfig(enablePasswordExpiration: true);
        $user = $this->createUser('testuser', 'test@example.com');
        $userId = (int) $user->getId();
        $passwordHasher = new PasswordHasher();
        $user->setPasswordHash($passwordHasher->hash('originalpass'));
        $user->save();
        (new PasswordHistoryService($passwordHasher, $config))->record($user);

        $controller = $this->createController($config);
        $request = (new ServerRequest('PUT', '/'))->withParsedBody(['password' => 'originalpass']);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(['error' => 'This password has been used recently. Please choose a different one.'], 400)
            ->willReturn($response);

        $result = $controller->update($request, $userId);

        $this->assertSame($response, $result);
    }

    public function testViewFound(): void
    {
        $user = $this->createUser('testuser', 'test@example.com');
        $userId = (int) $user->getId();

        $controller = $this->createController();

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

        $result = $controller->view($userId);

        $this->assertSame($response, $result);
    }

    public function testViewNotFound(): void
    {
        $controller = $this->createController();

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(['error' => 'Not found'], 404)
            ->willReturn($response);

        $result = $controller->view(999999);

        $this->assertSame($response, $result);
    }

    private function createController(?ModuleConfig $config = null): UserController
    {
        $config ??= $this->config;

        return new UserController(
            translator: $this->translator,
            config: $config,
            responseFactory: $this->responseFactory,
            passwordGenerator: $this->passwordGenerator,
            passwordHistoryService: new PasswordHistoryService(new PasswordHasher(), $config),
            userCreationHelper: $this->userCreationHelper,
        );
    }

    private function createUser(string $username, string $email): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        return $user;
    }
}
