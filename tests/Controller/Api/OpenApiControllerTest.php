<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Api;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\Api\OpenApiController;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;

#[AllowMockObjectsWithoutExpectations]
final class OpenApiControllerTest extends TestCase
{
    private DataResponseFactoryInterface&MockObject $responseFactory;
    private FakeUrlGenerator $url;

    protected function setUp(): void
    {
        $this->responseFactory = $this->createMock(DataResponseFactoryInterface::class);
        $this->url = new FakeUrlGenerator();
        $this->url->setUrl('voyti/api-v1-users-index', '/api/v1/users');
    }

    public function testIndexDefinesEndpoints(): void
    {
        $spec = $this->captureSpec();

        // List users: GET /users with pagination
        $listGet = $spec['paths']['/users']['get'];
        self::assertSame('listUsers', $listGet['operationId']);
        self::assertSame('List users', $listGet['summary']);
        self::assertSame(['Users'], $listGet['tags']);
        self::assertSame(5, count($listGet['parameters']));
        // Verify all parameter names present to catch ArrayItemRemoval mutations
        $paramNames = array_map(fn($p) => $p['name'], $listGet['parameters']);
        self::assertContains('username', $paramNames);
        self::assertContains('email', $paramNames);
        self::assertContains('status', $paramNames);
        self::assertContains('page', $paramNames);
        self::assertContains('perPage', $paramNames);
        self::assertSame('Paginated list of users', $listGet['responses']['200']['description']);
        self::assertSame('#/components/schemas/PaginatedUsers', $listGet['responses']['200']['content']['application/json']['schema']['$ref']);
        $pageParam = null;
        $perPageParam = null;
        foreach ($listGet['parameters'] as $param) {
            if ($param['name'] === 'page') {
                $pageParam = $param;
            } elseif ($param['name'] === 'perPage') {
                $perPageParam = $param;
            }
        }
        self::assertNotNull($pageParam, 'page parameter must be defined');
        self::assertSame('query', $pageParam['in']);
        self::assertSame('integer', $pageParam['schema']['type']);
        self::assertSame(1, $pageParam['schema']['default']);
        self::assertNotNull($perPageParam, 'perPage parameter must be defined');
        self::assertSame('query', $perPageParam['in']);
        self::assertSame('integer', $perPageParam['schema']['type']);
        self::assertSame(25, $perPageParam['schema']['default']);
        self::assertSame(100, $perPageParam['schema']['maximum']);

        // Create user: POST /users
        $createPost = $spec['paths']['/users']['post'];
        self::assertSame('createUser', $createPost['operationId']);
        self::assertSame('Create a user', $createPost['summary']);
        self::assertSame(['Users'], $createPost['tags']);
        self::assertArrayHasKey('requestBody', $createPost, 'POST /users must have requestBody');
        self::assertTrue($createPost['requestBody']['required'], 'POST /users requestBody must be required');
        self::assertSame('#/components/schemas/UserCreateRequest', $createPost['requestBody']['content']['application/json']['schema']['$ref']);
        self::assertSame('User created', $createPost['responses']['201']['description']);
        self::assertSame('#/components/schemas/UserCreatedResponse', $createPost['responses']['201']['content']['application/json']['schema']['$ref']);
        self::assertSame('Validation error', $createPost['responses']['400']['description']);
        self::assertSame('#/components/schemas/ErrorResponse', $createPost['responses']['400']['content']['application/json']['schema']['$ref']);

        // Get user: GET /users/{id}
        $getUser = $spec['paths']['/users/{id}']['get'];
        self::assertSame('getUser', $getUser['operationId']);
        self::assertSame('Get a user by ID', $getUser['summary']);
        self::assertSame(['Users'], $getUser['tags']);
        self::assertSame('id', $getUser['parameters'][0]['name']);
        self::assertSame('path', $getUser['parameters'][0]['in']);
        self::assertTrue($getUser['parameters'][0]['required']);
        self::assertSame('integer', $getUser['parameters'][0]['schema']['type']);
        self::assertSame('User details', $getUser['responses']['200']['description']);
        self::assertSame('#/components/schemas/User', $getUser['responses']['200']['content']['application/json']['schema']['$ref']);
        self::assertSame('User not found', $getUser['responses']['404']['description']);
        self::assertSame('#/components/schemas/ErrorResponse', $getUser['responses']['404']['content']['application/json']['schema']['$ref']);

        // Update user: PATCH /users/{id}
        $updatePatch = $spec['paths']['/users/{id}']['patch'];
        self::assertSame('updateUser', $updatePatch['operationId']);
        self::assertSame('Update a user', $updatePatch['summary']);
        self::assertSame(['Users'], $updatePatch['tags']);
        self::assertSame('id', $updatePatch['parameters'][0]['name']);
        self::assertSame('path', $updatePatch['parameters'][0]['in']);
        self::assertTrue($updatePatch['parameters'][0]['required']);
        self::assertSame('integer', $updatePatch['parameters'][0]['schema']['type']);
        self::assertArrayHasKey('requestBody', $updatePatch, 'PATCH /users/{id} must have requestBody');
        self::assertSame('#/components/schemas/UserUpdateRequest', $updatePatch['requestBody']['content']['application/json']['schema']['$ref']);
        self::assertSame('User updated', $updatePatch['responses']['200']['description']);
        self::assertSame('#/components/schemas/UserUpdatedResponse', $updatePatch['responses']['200']['content']['application/json']['schema']['$ref']);
        self::assertSame('Validation error', $updatePatch['responses']['400']['description']);
        self::assertSame('#/components/schemas/ErrorResponse', $updatePatch['responses']['400']['content']['application/json']['schema']['$ref']);

        // Delete user: DELETE /users/{id}
        $deleteOp = $spec['paths']['/users/{id}']['delete'];
        self::assertSame('deleteUser', $deleteOp['operationId']);
        self::assertSame('Delete a user', $deleteOp['summary']);
        self::assertSame(['Users'], $deleteOp['tags']);
        self::assertSame('id', $deleteOp['parameters'][0]['name']);
        self::assertSame('path', $deleteOp['parameters'][0]['in']);
        self::assertTrue($deleteOp['parameters'][0]['required']);
        self::assertSame('integer', $deleteOp['parameters'][0]['schema']['type']);
        self::assertSame('User deleted', $deleteOp['responses']['200']['description']);
        self::assertSame('#/components/schemas/MessageResponse', $deleteOp['responses']['200']['content']['application/json']['schema']['$ref']);
        self::assertSame('User not found', $deleteOp['responses']['404']['description']);
        self::assertSame('#/components/schemas/ErrorResponse', $deleteOp['responses']['404']['content']['application/json']['schema']['$ref']);
    }

    public function testIndexDefinesMetadata(): void
    {
        $spec = $this->captureSpec();

        // OpenAPI version and info
        self::assertSame('3.1.0', $spec['openapi']);
        self::assertSame('Voyti API', $spec['info']['title']);
        self::assertSame('1.0.0', $spec['info']['version']);
        self::assertSame('User management, authentication, and authorization REST API.', $spec['info']['description']);

        // Servers
        self::assertSame('/api/v1', $spec['servers'][0]['url']);
        self::assertSame('REST API', $spec['servers'][0]['description']);

        // Bearer auth
        self::assertSame('http', $spec['components']['securitySchemes']['bearerAuth']['type']);
        self::assertSame('bearer', $spec['components']['securitySchemes']['bearerAuth']['scheme']);
        self::assertSame('JWT', $spec['components']['securitySchemes']['bearerAuth']['bearerFormat']);
        self::assertSame([['bearerAuth' => []]], $spec['security']);
    }

    public function testIndexDefinesSchemas(): void
    {
        $spec = $this->captureSpec();

        // Error and message schemas
        $errorSchema = $spec['components']['schemas']['ErrorResponse'];
        self::assertSame('object', $errorSchema['type']);
        self::assertSame(['error'], $errorSchema['required']);
        self::assertSame('string', $errorSchema['properties']['error']['type']);

        $msgSchema = $spec['components']['schemas']['MessageResponse'];
        self::assertSame('object', $msgSchema['type']);
        self::assertSame(['message'], $msgSchema['required']);
        self::assertSame('string', $msgSchema['properties']['message']['type']);

        // User schema
        $userSchema = $spec['components']['schemas']['User'];
        self::assertSame('object', $userSchema['type']);
        self::assertSame('integer', $userSchema['properties']['id']['type']);
        self::assertSame('string', $userSchema['properties']['username']['type']);
        self::assertSame('string', $userSchema['properties']['email']['type']);
        self::assertSame('email', $userSchema['properties']['email']['format']);
        self::assertSame('integer', $userSchema['properties']['createdAt']['type']);
        self::assertSame(['integer', 'null'], $userSchema['properties']['confirmedAt']['type']);
        self::assertSame(['integer', 'null'], $userSchema['properties']['blockedAt']['type']);

        // User create request schema
        $createReqSchema = $spec['components']['schemas']['UserCreateRequest'];
        self::assertSame('object', $createReqSchema['type']);
        self::assertSame(['username', 'email'], $createReqSchema['required']);
        self::assertSame('string', $createReqSchema['properties']['username']['type']);
        self::assertSame('string', $createReqSchema['properties']['email']['type']);
        self::assertSame('email', $createReqSchema['properties']['email']['format']);
        self::assertSame('string', $createReqSchema['properties']['password']['type']);
        self::assertSame('Generated if omitted', $createReqSchema['properties']['password']['description']);

        // User created response schema
        $createRespSchema = $spec['components']['schemas']['UserCreatedResponse'];
        self::assertSame('object', $createRespSchema['type']);
        self::assertSame(['id', 'username', 'email', 'message'], $createRespSchema['required']);
        self::assertSame('integer', $createRespSchema['properties']['id']['type']);
        self::assertSame('string', $createRespSchema['properties']['username']['type']);
        self::assertSame('string', $createRespSchema['properties']['email']['type']);
        self::assertSame('email', $createRespSchema['properties']['email']['format']);
        self::assertSame('string', $createRespSchema['properties']['message']['type']);

        // User update request schema
        $updateReqSchema = $spec['components']['schemas']['UserUpdateRequest'];
        self::assertSame('object', $updateReqSchema['type']);
        self::assertArrayNotHasKey('required', $updateReqSchema);
        self::assertSame('string', $updateReqSchema['properties']['username']['type']);
        self::assertSame('string', $updateReqSchema['properties']['email']['type']);
        self::assertSame('email', $updateReqSchema['properties']['email']['format']);
        self::assertSame('string', $updateReqSchema['properties']['password']['type']);

        // User updated response schema
        $updateRespSchema = $spec['components']['schemas']['UserUpdatedResponse'];
        self::assertSame('object', $updateRespSchema['type']);
        self::assertSame(['id', 'username', 'email', 'message'], $updateRespSchema['required']);
        self::assertSame('integer', $updateRespSchema['properties']['id']['type']);
        self::assertSame('string', $updateRespSchema['properties']['username']['type']);
        self::assertSame('string', $updateRespSchema['properties']['email']['type']);
        self::assertSame('email', $updateRespSchema['properties']['email']['format']);
        self::assertSame('string', $updateRespSchema['properties']['message']['type']);

        // Paginated users schema
        $paginatedSchema = $spec['components']['schemas']['PaginatedUsers'];
        self::assertSame('object', $paginatedSchema['type']);
        self::assertSame(['items', 'totalCount', 'currentPage', 'pageSize', 'totalPages'], $paginatedSchema['required']);
        self::assertSame('array', $paginatedSchema['properties']['items']['type']);
        self::assertSame('#/components/schemas/User', $paginatedSchema['properties']['items']['items']['$ref']);
        self::assertSame('integer', $paginatedSchema['properties']['totalCount']['type']);
        self::assertSame('integer', $paginatedSchema['properties']['currentPage']['type']);
        self::assertSame('integer', $paginatedSchema['properties']['pageSize']['type']);
        self::assertSame('integer', $paginatedSchema['properties']['totalPages']['type']);
    }

    private function assertSchemaRefPresent(array $schema, string $expectedRef, string $context): void
    {
        self::assertArrayHasKey('$ref', $schema, "Schema $ref missing in $context: " . json_encode($schema));
        self::assertSame($expectedRef, $schema['$ref'], "Schema $ref mismatch in $context");
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

    private function createController(?VoytiConfig $config = null): OpenApiController
    {
        return new OpenApiController(
            responseFactory: $this->responseFactory,
            config: $config ?? VoytiConfigFactory::create(),
            url: $this->url,
        );
    }
}
