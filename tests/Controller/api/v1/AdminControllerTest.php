<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\api\v1;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\Controller\api\v1\AdminController;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\DataResponse\Middleware\JsonDataResponseMiddleware;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactory;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Translator\TranslatorInterface;

#[AllowMockObjectsWithoutExpectations]
final class AdminControllerTest extends TestCase
{
    use DatabaseSetupTrait;

    private ModuleConfig $config;
    private PasswordHasher $passwordHasher;
    private DataResponseFactoryInterface&MockObject $responseFactory;
    private TranslatorInterface $translator;
    private UserRepository&MockObject $userRepository;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->config = new ModuleConfig();
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->translator = $this->createTranslator();
        $this->passwordHasher = new PasswordHasher();
        $this->responseFactory = $this->createMock(DataResponseFactoryInterface::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testCreateEmailAlreadyExists(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['email' => 'existing@example.com', 'username' => 'newuser', 'password' => 'secret123']);

        $existingUser = $this->createMock(User::class);
        $this->userRepository->method('findByEmail')->willReturn($existingUser);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(['error' => 'Email already exists'], 400)
            ->willReturn($response);

        $result = $controller->create($request);

        $this->assertSame($response, $result);
    }

    public function testCreateResponseIsJsonFormattableThroughRealPipeline(): void
    {
        $controller = new AdminController(
            translator: $this->translator,
            userRepository: new UserRepository(),
            passwordHasher: $this->passwordHasher,
            config: $this->config,
            responseFactory: new DataResponseFactory(new Psr17Factory()),
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

        $this->userRepository->method('findByEmail')->willReturn(null);
        $this->userRepository->method('findByUsername')->willReturn(null);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(self::callback('is_array'), 201)
            ->willReturn($response);

        $result = $controller->create($request);

        $this->assertSame($response, $result);
    }

    public function testCreateUsernameAlreadyExists(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['email' => 'new@example.com', 'username' => 'existinguser', 'password' => 'secret123']);

        $this->userRepository->method('findByEmail')->willReturn(null);
        $existingUser = $this->createMock(User::class);
        $this->userRepository->method('findByUsername')->willReturn($existingUser);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(['error' => 'Username already exists'], 400)
            ->willReturn($response);

        $result = $controller->create($request);

        $this->assertSame($response, $result);
    }

    public function testDeleteNotFound(): void
    {
        $controller = $this->createController();

        $this->userRepository->method('findById')->willReturn(null);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(self::callback('is_array'), 404)
            ->willReturn($response);

        $result = $controller->delete(999);

        $this->assertSame($response, $result);
    }

    public function testDeleteSuccess(): void
    {
        $controller = $this->createController();

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $this->userRepository->method('findById')->willReturn($user);
        $this->userRepository->expects($this->once())->method('delete')->with($user);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(self::callback('is_array'))
            ->willReturn($response);

        $result = $controller->delete(1);

        $this->assertSame($response, $result);
    }

    public function testIndexReturnsUsers(): void
    {
        $controller = $this->createController();

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $user->method('getUsername')->willReturn('testuser');
        $user->method('getEmail')->willReturn('test@example.com');
        $user->method('getCreatedAt')->willReturn(1000000);
        $user->method('getConfirmedAt')->willReturn(1000000);
        $user->method('getBlockedAt')->willReturn(null);

        $this->userRepository->method('findAllUsers')->willReturn([$user]);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(self::callback('is_array'))
            ->willReturn($response);

        $result = $controller->index();

        $this->assertSame($response, $result);
    }

    public function testUpdateNotFound(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('PUT', '/');

        $this->userRepository->method('findById')->willReturn(null);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(self::callback('is_array'), 404)
            ->willReturn($response);

        $result = $controller->update($request, 999);

        $this->assertSame($response, $result);
    }

    public function testUpdateSuccess(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('PUT', '/'))->withParsedBody(['username' => 'updated', 'email' => 'updated@example.com']);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $user->method('getUsername')->willReturn('testuser');
        $user->method('getEmail')->willReturn('test@example.com');
        $user->expects($this->once())->method('setUsername');
        $user->expects($this->once())->method('setEmail');
        $user->expects($this->once())->method('setUpdatedAt');
        $user->expects($this->once())->method('save');
        $this->userRepository->method('findById')->willReturn($user);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(self::callback('is_array'))
            ->willReturn($response);

        $result = $controller->update($request, 1);

        $this->assertSame($response, $result);
    }

    public function testUpdateWithPassword(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('PUT', '/'))->withParsedBody(['username' => 'updated', 'email' => 'updated@example.com', 'password' => 'newpass']);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $user->method('getUsername')->willReturn('testuser');
        $user->method('getEmail')->willReturn('test@example.com');
        $user->expects($this->once())->method('setPasswordHash');
        $user->expects($this->once())->method('setPasswordChangedAt');
        $user->expects($this->once())->method('save');
        $this->userRepository->method('findById')->willReturn($user);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(self::callback('is_array'))
            ->willReturn($response);

        $result = $controller->update($request, 1);

        $this->assertSame($response, $result);
    }

    public function testViewFound(): void
    {
        $controller = $this->createController();

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');
        $user->method('getUsername')->willReturn('testuser');
        $user->method('getEmail')->willReturn('test@example.com');
        $user->method('getCreatedAt')->willReturn(1000000);
        $this->userRepository->method('findById')->willReturn($user);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(self::callback('is_array'))
            ->willReturn($response);

        $result = $controller->view(1);

        $this->assertSame($response, $result);
    }

    public function testViewNotFound(): void
    {
        $controller = $this->createController();

        $this->userRepository->method('findById')->willReturn(null);

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(self::callback('is_array'), 404)
            ->willReturn($response);

        $result = $controller->view(999);

        $this->assertSame($response, $result);
    }

    private function createController(): AdminController
    {
        return new AdminController(
            translator: $this->translator,
            userRepository: $this->userRepository,
            passwordHasher: $this->passwordHasher,
            config: $this->config,
            responseFactory: $this->responseFactory,
        );
    }
}
