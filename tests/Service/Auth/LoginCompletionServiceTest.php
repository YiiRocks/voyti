<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Auth;

use Nyholm\Psr7\ServerRequest;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Event\Auth\BeforeLoginEvent;
use YiiRocks\Voyti\Service\Auth\LoginCompletionService;
use YiiRocks\Voyti\tests\Support\CurrentUserTrait;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;
use YiiRocks\Voyti\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use Yiisoft\User\CurrentUser;

final class LoginCompletionServiceTest extends DatabaseTestCase
{
    use CurrentUserTrait;
    use TestContainerTrait;
    use UserFactoryTrait;

    public function testComplete(): void
    {
        $user = $this->createUser(username: 'complete_user', email: 'complete_user@example.com');
        $currentUser = $this->createCurrentUser();
        $eventDispatcher = new EventCaptureDispatcher();
        $service = $this->getTestContainer([
            CurrentUser::class => $currentUser,
            EventDispatcherInterface::class => $eventDispatcher,
        ])->get(LoginCompletionService::class);

        $result = $service->complete($user, false, new ServerRequest('POST', '/'));

        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame((int) $user->getId(), (int) $currentUser->getId());
        $this->assertTrue($eventDispatcher->hasEvent(BeforeLoginEvent::class));
        $this->assertTrue($eventDispatcher->hasEvent(AfterLoginEvent::class));
    }
}
