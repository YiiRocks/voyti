<?php

declare(strict_types=1);

use YiiRocks\Voyti\Controller\Api\OpenApiController;
use YiiRocks\Voyti\Controller\Api\V1\User\UserController;
use YiiRocks\Voyti\Middleware\AccessRuleMiddleware;
use YiiRocks\Voyti\Middleware\ApiTokenAuthenticationMiddleware;
use Yiisoft\DataResponse\Middleware\JsonDataResponseMiddleware;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;

return [
    Group::create()
        ->middleware(JsonDataResponseMiddleware::class)
        ->routes(
            Route::get('openapi.json')->name('voyti/api-openapi')->action([OpenApiController::class, 'index']),
            Group::create('v1/')
                ->middleware(ApiTokenAuthenticationMiddleware::class, AccessRuleMiddleware::class)
                ->routes(
                    Route::get('users')->name('voyti/api-v1-users-index')->action([UserController::class, 'index']),
                    Route::get('users/{id:\d+}')->name('voyti/api-v1-users-view')->action([UserController::class, 'view']),
                    Route::post('users')->name('voyti/api-v1-users-create')->action([UserController::class, 'create']),
                    Route::patch('users/{id:\d+}')->name('voyti/api-v1-users-update')->action([UserController::class, 'update']),
                    Route::delete('users/{id:\d+}')->name('voyti/api-v1-users-delete')->action([UserController::class, 'delete']),
                ),
        ),
];
