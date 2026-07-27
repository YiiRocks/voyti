<?php

declare(strict_types=1);

use YiiRocks\Voyti\Controller;
use YiiRocks\Voyti\Middleware\AccessRuleMiddleware;
use YiiRocks\Voyti\Middleware\ApiTokenAuthenticationMiddleware;
use Yiisoft\DataResponse\Middleware\JsonDataResponseMiddleware;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;

return [
    Group::create()
        ->middleware(JsonDataResponseMiddleware::class)
        ->routes(
            Route::get('openapi.json')->name('voyti/api-openapi')->action([Controller\Api\OpenApiController::class, 'index']),
            Group::create('v1/')
                ->middleware(ApiTokenAuthenticationMiddleware::class, AccessRuleMiddleware::class)
                ->routes(
                    Route::get('users')->name('voyti/api-v1-users-index')->action([Controller\Api\V1\User\UserController::class, 'index']),
                    Route::get('users/{id:\d+}')->name('voyti/api-v1-users-view')->action([Controller\Api\V1\User\UserController::class, 'view']),
                    Route::post('users')->name('voyti/api-v1-users-create')->action([Controller\Api\V1\User\UserController::class, 'create']),
                    Route::patch('users/{id:\d+}')->name('voyti/api-v1-users-update')->action([Controller\Api\V1\User\UserController::class, 'update']),
                    Route::delete('users/{id:\d+}')->name('voyti/api-v1-users-delete')->action([Controller\Api\V1\User\UserController::class, 'delete']),
                ),
        ),
];
