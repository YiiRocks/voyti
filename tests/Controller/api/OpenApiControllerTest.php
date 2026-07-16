<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\api;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\api\OpenApiController;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;

#[AllowMockObjectsWithoutExpectations]
final class OpenApiControllerTest extends TestCase
{
    private DataResponseFactoryInterface&\PHPUnit\Framework\MockObject\MockObject $responseFactory;

    protected function setUp(): void
    {
        $this->responseFactory = $this->createMock(DataResponseFactoryInterface::class);
    }

    public function testIndexDefinesErrorResponseSchema(): void
    {
        $spec = $this->captureSpec();
        $schema = $spec['components']['schemas']['ErrorResponse'];

        self::assertSame('object', $schema['type']);
        self::assertSame(['error'], $schema['required']);
        self::assertSame('string', $schema['properties']['error']['type']);
    }

    public function testIndexDefinesMessageResponseSchema(): void
    {
        $spec = $this->captureSpec();
        $schema = $spec['components']['schemas']['MessageResponse'];

        self::assertSame('object', $schema['type']);
        self::assertSame(['message'], $schema['required']);
        self::assertSame('string', $schema['properties']['message']['type']);
    }

    public function testIndexDefinesPaginatedUsersSchema(): void
    {
        $spec = $this->captureSpec();
        $schema = $spec['components']['schemas']['PaginatedUsers'];

        self::assertSame('object', $schema['type']);
        self::assertSame(['items', 'totalCount', 'currentPage', 'pageSize', 'totalPages'], $schema['required']);
        self::assertSame('array', $schema['properties']['items']['type']);
        self::assertSame('#/components/schemas/User', $schema['properties']['items']['items']['$ref']);
        self::assertSame('integer', $schema['properties']['totalCount']['type']);
        self::assertSame('integer', $schema['properties']['currentPage']['type']);
        self::assertSame('integer', $schema['properties']['pageSize']['type']);
        self::assertSame('integer', $schema['properties']['totalPages']['type']);
    }

    public function testIndexDefinesServers(): void
    {
        $spec = $this->captureSpec();

        self::assertSame('/api/v1', $spec['servers'][0]['url']);
        self::assertSame('REST API', $spec['servers'][0]['description']);
    }

    public function testIndexDefinesUserCreatedResponseSchema(): void
    {
        $spec = $this->captureSpec();
        $schema = $spec['components']['schemas']['UserCreatedResponse'];

        self::assertSame('object', $schema['type']);
        self::assertSame(['id', 'username', 'email', 'message'], $schema['required']);
        self::assertSame('integer', $schema['properties']['id']['type']);
        self::assertSame('string', $schema['properties']['username']['type']);
        self::assertSame('string', $schema['properties']['email']['type']);
        self::assertSame('email', $schema['properties']['email']['format']);
        self::assertSame('string', $schema['properties']['message']['type']);
    }

    public function testIndexDefinesUserCreateRequestSchema(): void
    {
        $spec = $this->captureSpec();
        $schema = $spec['components']['schemas']['UserCreateRequest'];

        self::assertSame('object', $schema['type']);
        self::assertSame(['username', 'email'], $schema['required']);
        self::assertSame('string', $schema['properties']['username']['type']);
        self::assertSame('string', $schema['properties']['email']['type']);
        self::assertSame('email', $schema['properties']['email']['format']);
        self::assertSame('string', $schema['properties']['password']['type']);
        self::assertSame('Generated if omitted', $schema['properties']['password']['description']);
    }

    public function testIndexDefinesUserSchema(): void
    {
        $spec = $this->captureSpec();
        $schema = $spec['components']['schemas']['User'];

        self::assertSame('object', $schema['type']);
        self::assertSame('integer', $schema['properties']['id']['type']);
        self::assertSame('string', $schema['properties']['username']['type']);
        self::assertSame('string', $schema['properties']['email']['type']);
        self::assertSame('email', $schema['properties']['email']['format']);
        self::assertSame('integer', $schema['properties']['createdAt']['type']);
        self::assertSame(['integer', 'null'], $schema['properties']['confirmedAt']['type']);
        self::assertSame(['integer', 'null'], $schema['properties']['blockedAt']['type']);
    }

    public function testIndexDefinesUserUpdatedResponseSchema(): void
    {
        $spec = $this->captureSpec();
        $schema = $spec['components']['schemas']['UserUpdatedResponse'];

        self::assertSame('object', $schema['type']);
        self::assertSame(['id', 'username', 'email', 'message'], $schema['required']);
        self::assertSame('integer', $schema['properties']['id']['type']);
        self::assertSame('string', $schema['properties']['username']['type']);
        self::assertSame('string', $schema['properties']['email']['type']);
        self::assertSame('email', $schema['properties']['email']['format']);
        self::assertSame('string', $schema['properties']['message']['type']);
    }

    public function testIndexDefinesUserUpdateRequestSchema(): void
    {
        $spec = $this->captureSpec();
        $schema = $spec['components']['schemas']['UserUpdateRequest'];

        self::assertSame('object', $schema['type']);
        self::assertArrayNotHasKey('required', $schema);
        self::assertSame('string', $schema['properties']['username']['type']);
        self::assertSame('string', $schema['properties']['email']['type']);
        self::assertSame('email', $schema['properties']['email']['format']);
        self::assertSame('string', $schema['properties']['password']['type']);
    }

    public function testIndexDocumentsCreateUserEndpoint(): void
    {
        $spec = $this->captureSpec();
        $post = $spec['paths']['/users']['post'];

        self::assertSame('createUser', $post['operationId']);
        self::assertSame('Create a user', $post['summary']);
        self::assertSame(['Users'], $post['tags']);
        self::assertTrue($post['requestBody']['required']);
        self::assertSame('#/components/schemas/UserCreateRequest', $post['requestBody']['content']['application/json']['schema']['$ref']);
        self::assertSame('User created', $post['responses']['201']['description']);
        self::assertSame('#/components/schemas/UserCreatedResponse', $post['responses']['201']['content']['application/json']['schema']['$ref']);
        self::assertSame('Validation error', $post['responses']['400']['description']);
        self::assertSame('#/components/schemas/ErrorResponse', $post['responses']['400']['content']['application/json']['schema']['$ref']);
    }

    public function testIndexDocumentsDeleteUserEndpoint(): void
    {
        $spec = $this->captureSpec();
        $delete = $spec['paths']['/users/{id}']['delete'];

        self::assertSame('deleteUser', $delete['operationId']);
        self::assertSame('Delete a user', $delete['summary']);
        self::assertSame(['Users'], $delete['tags']);
        self::assertSame('id', $delete['parameters'][0]['name']);
        self::assertSame('path', $delete['parameters'][0]['in']);
        self::assertTrue($delete['parameters'][0]['required']);
        self::assertSame('integer', $delete['parameters'][0]['schema']['type']);
        self::assertSame('User deleted', $delete['responses']['200']['description']);
        self::assertSame('#/components/schemas/MessageResponse', $delete['responses']['200']['content']['application/json']['schema']['$ref']);
        self::assertSame('User not found', $delete['responses']['404']['description']);
        self::assertSame('#/components/schemas/ErrorResponse', $delete['responses']['404']['content']['application/json']['schema']['$ref']);
    }

    public function testIndexDocumentsGetUserEndpoint(): void
    {
        $spec = $this->captureSpec();
        $get = $spec['paths']['/users/{id}']['get'];

        self::assertSame('getUser', $get['operationId']);
        self::assertSame('Get a user by ID', $get['summary']);
        self::assertSame(['Users'], $get['tags']);
        self::assertSame('id', $get['parameters'][0]['name']);
        self::assertSame('path', $get['parameters'][0]['in']);
        self::assertTrue($get['parameters'][0]['required']);
        self::assertSame('integer', $get['parameters'][0]['schema']['type']);
        self::assertSame('User details', $get['responses']['200']['description']);
        self::assertSame('#/components/schemas/User', $get['responses']['200']['content']['application/json']['schema']['$ref']);
        self::assertSame('User not found', $get['responses']['404']['description']);
        self::assertSame('#/components/schemas/ErrorResponse', $get['responses']['404']['content']['application/json']['schema']['$ref']);
    }

    public function testIndexDocumentsUpdateUserEndpoint(): void
    {
        $spec = $this->captureSpec();
        $patch = $spec['paths']['/users/{id}']['patch'];

        self::assertSame('updateUser', $patch['operationId']);
        self::assertSame('Update a user', $patch['summary']);
        self::assertSame(['Users'], $patch['tags']);
        self::assertSame('id', $patch['parameters'][0]['name']);
        self::assertSame('path', $patch['parameters'][0]['in']);
        self::assertTrue($patch['parameters'][0]['required']);
        self::assertSame('integer', $patch['parameters'][0]['schema']['type']);
        self::assertTrue($patch['requestBody']['required']);
        self::assertSame('#/components/schemas/UserUpdateRequest', $patch['requestBody']['content']['application/json']['schema']['$ref']);
        self::assertSame('User updated', $patch['responses']['200']['description']);
        self::assertSame('#/components/schemas/UserUpdatedResponse', $patch['responses']['200']['content']['application/json']['schema']['$ref']);
        self::assertSame('Validation error', $patch['responses']['400']['description']);
        self::assertSame('#/components/schemas/ErrorResponse', $patch['responses']['400']['content']['application/json']['schema']['$ref']);
        self::assertSame('User not found', $patch['responses']['404']['description']);
        self::assertSame('#/components/schemas/ErrorResponse', $patch['responses']['404']['content']['application/json']['schema']['$ref']);
    }

    public function testIndexDocumentsUserListEndpoint(): void
    {
        $spec = $this->captureSpec();
        $get = $spec['paths']['/users']['get'];

        self::assertSame('listUsers', $get['operationId']);
        self::assertSame('List users', $get['summary']);
        self::assertSame(['Users'], $get['tags']);
        self::assertSame('username', $get['parameters'][0]['name']);
        self::assertSame('query', $get['parameters'][0]['in']);
        self::assertSame('string', $get['parameters'][0]['schema']['type']);
        self::assertSame('email', $get['parameters'][1]['name']);
        self::assertSame('string', $get['parameters'][1]['schema']['type']);
        self::assertSame('status', $get['parameters'][2]['name']);
        self::assertSame('string', $get['parameters'][2]['schema']['type']);
        self::assertSame('page', $get['parameters'][3]['name']);
        self::assertSame('integer', $get['parameters'][3]['schema']['type']);
        self::assertSame(1, $get['parameters'][3]['schema']['default']);
        self::assertSame('Paginated list of users', $get['responses']['200']['description']);
        self::assertSame('application/json', array_key_first($get['responses']['200']['content']));
        self::assertSame('#/components/schemas/PaginatedUsers', $get['responses']['200']['content']['application/json']['schema']['$ref']);
    }

    public function testIndexRequiresBearerAuth(): void
    {
        $spec = $this->captureSpec();

        self::assertSame('http', $spec['components']['securitySchemes']['bearerAuth']['type']);
        self::assertSame('bearer', $spec['components']['securitySchemes']['bearerAuth']['scheme']);
        self::assertSame('JWT', $spec['components']['securitySchemes']['bearerAuth']['bearerFormat']);
        self::assertSame([['bearerAuth' => []]], $spec['security']);
    }

    public function testIndexReturnsOpenApi3Dot1(): void
    {
        $spec = $this->captureSpec();

        self::assertSame('3.1.0', $spec['openapi']);
        self::assertSame('Voyti API', $spec['info']['title']);
        self::assertSame('1.0.0', $spec['info']['version']);
        self::assertSame('User management, authentication, and authorization REST API.', $spec['info']['description']);
    }

    private function captureSpec(): array
    {
        $captured = null;
        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')
            ->willReturnCallback(static function (array $data) use (&$captured, $response): ResponseInterface {
                $captured = $data;

                return $response;
            });

        $this->createController()->index();

        self::assertNotNull($captured, 'createResponse was not called');

        return $captured;
    }

    private function createController(?ModuleConfig $config = null): OpenApiController
    {
        return new OpenApiController(
            responseFactory: $this->responseFactory,
            config: $config ?? new ModuleConfig(),
        );
    }
}
