<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\api;

use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;

final readonly class OpenApiController
{
    public function __construct(
        private DataResponseFactoryInterface $responseFactory,
        private ModuleConfig $config,
    ) {
    }

    public function index(): ResponseInterface
    {
        return $this->responseFactory->createResponse([
            'openapi' => '3.1.0',
            'info' => [
                'title' => $this->config->appName . ' API',
                'version' => '1.0.0',
                'description' => 'User management, authentication, and authorization REST API.',
            ],
            'servers' => [
                ['url' => '/' . $this->config->adminRestPrefix . '/v1', 'description' => 'REST API'],
            ],
            'paths' => [
                '/users' => [
                    'get' => [
                        'operationId' => 'listUsers',
                        'summary' => 'List users',
                        'tags' => ['Users'],
                        'parameters' => [
                            ['name' => 'username', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'email', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 1]],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Paginated list of users',
                                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/PaginatedUsers']]],
                            ],
                        ],
                    ],
                    'post' => [
                        'operationId' => 'createUser',
                        'summary' => 'Create a user',
                        'tags' => ['Users'],
                        'requestBody' => [
                            'required' => true,
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/UserCreateRequest']]],
                        ],
                        'responses' => [
                            '201' => [
                                'description' => 'User created',
                                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/UserCreatedResponse']]],
                            ],
                            '400' => [
                                'description' => 'Validation error',
                                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ErrorResponse']]],
                            ],
                        ],
                    ],
                ],
                '/users/{id}' => [
                    'get' => [
                        'operationId' => 'getUser',
                        'summary' => 'Get a user by ID',
                        'tags' => ['Users'],
                        'parameters' => [
                            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'User details',
                                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/User']]],
                            ],
                            '404' => [
                                'description' => 'User not found',
                                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ErrorResponse']]],
                            ],
                        ],
                    ],
                    'patch' => [
                        'operationId' => 'updateUser',
                        'summary' => 'Update a user',
                        'tags' => ['Users'],
                        'parameters' => [
                            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                        ],
                        'requestBody' => [
                            'required' => true,
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/UserUpdateRequest']]],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'User updated',
                                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/UserUpdatedResponse']]],
                            ],
                            '400' => [
                                'description' => 'Validation error',
                                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ErrorResponse']]],
                            ],
                            '404' => [
                                'description' => 'User not found',
                                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ErrorResponse']]],
                            ],
                        ],
                    ],
                    'delete' => [
                        'operationId' => 'deleteUser',
                        'summary' => 'Delete a user',
                        'tags' => ['Users'],
                        'parameters' => [
                            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'User deleted',
                                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/MessageResponse']]],
                            ],
                            '404' => [
                                'description' => 'User not found',
                                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ErrorResponse']]],
                            ],
                        ],
                    ],
                ],
            ],
            'components' => [
                'schemas' => [
                    'User' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'username' => ['type' => 'string'],
                            'email' => ['type' => 'string', 'format' => 'email'],
                            'createdAt' => ['type' => 'integer'],
                            'confirmedAt' => ['type' => ['integer', 'null']],
                            'blockedAt' => ['type' => ['integer', 'null']],
                        ],
                    ],
                    'UserCreateRequest' => [
                        'type' => 'object',
                        'required' => ['username', 'email'],
                        'properties' => [
                            'username' => ['type' => 'string'],
                            'email' => ['type' => 'string', 'format' => 'email'],
                            'password' => ['type' => 'string', 'description' => 'Generated if omitted'],
                        ],
                    ],
                    'UserUpdateRequest' => [
                        'type' => 'object',
                        'properties' => [
                            'username' => ['type' => 'string'],
                            'email' => ['type' => 'string', 'format' => 'email'],
                            'password' => ['type' => 'string'],
                        ],
                    ],
                    'ErrorResponse' => [
                        'type' => 'object',
                        'required' => ['error'],
                        'properties' => [
                            'error' => ['type' => 'string'],
                        ],
                    ],
                    'MessageResponse' => [
                        'type' => 'object',
                        'required' => ['message'],
                        'properties' => [
                            'message' => ['type' => 'string'],
                        ],
                    ],
                    'UserCreatedResponse' => [
                        'type' => 'object',
                        'required' => ['id', 'username', 'email', 'message'],
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'username' => ['type' => 'string'],
                            'email' => ['type' => 'string', 'format' => 'email'],
                            'message' => ['type' => 'string'],
                        ],
                    ],
                    'UserUpdatedResponse' => [
                        'type' => 'object',
                        'required' => ['id', 'username', 'email', 'message'],
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'username' => ['type' => 'string'],
                            'email' => ['type' => 'string', 'format' => 'email'],
                            'message' => ['type' => 'string'],
                        ],
                    ],
                    'PaginatedUsers' => [
                        'type' => 'object',
                        'required' => ['items', 'totalCount', 'currentPage', 'pageSize', 'totalPages'],
                        'properties' => [
                            'items' => [
                                'type' => 'array',
                                'items' => ['$ref' => '#/components/schemas/User'],
                            ],
                            'totalCount' => ['type' => 'integer'],
                            'currentPage' => ['type' => 'integer'],
                            'pageSize' => ['type' => 'integer'],
                            'totalPages' => ['type' => 'integer'],
                        ],
                    ],
                ],
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                    ],
                ],
            ],
            'security' => [
                ['bearerAuth' => []],
            ],
        ]);
    }
}
