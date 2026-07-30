<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Auth;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSocialAccount;
use YiiRocks\Voyti\Service\Auth\PendingSocialAccountService;
use YiiRocks\Voyti\Service\Auth\SocialAuthCallbackService;
use YiiRocks\Voyti\Service\Auth\SocialUserAttributesNormalizer;
use YiiRocks\Voyti\Service\Auth\UserSocialAccountConnectService;
use YiiRocks\Voyti\Service\Auth\UserSocialAuthenticateService;
use YiiRocks\Voyti\Service\RememberMeCookieService;
use YiiRocks\Voyti\Service\ServiceResult;
use YiiRocks\Voyti\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;
use Yiisoft\Yii\AuthClient\AuthClientInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[AllowMockObjectsWithoutExpectations]
final class SocialAuthCallbackServiceTest extends TestCase
{
    use TestContainerTrait;

    private CurrentUser&MockObject $currentUser;
    private FlashInterface&MockObject $flash;
    private SocialUserAttributesNormalizer&MockObject $normalizer;
    private PendingSocialAccountService&MockObject $pendingSocialAccountService;
    private RememberMeCookieService&MockObject $rememberMeCookieService;
    private UserSocialAccountConnectService&MockObject $socialAccountConnectService;
    private UserSocialAuthenticateService&MockObject $socialAuthenticateService;
    private WebViewRenderer&MockObject $viewRenderer;

    protected function setUp(): void
    {
        $this->viewRenderer = $this->createMock(WebViewRenderer::class);
        $this->viewRenderer->method('withAddedInjections')->willReturnSelf();
        $this->currentUser = $this->createMock(CurrentUser::class);
        $this->flash = $this->createMock(FlashInterface::class);
        $this->rememberMeCookieService = $this->createMock(RememberMeCookieService::class);
        $this->pendingSocialAccountService = $this->createMock(PendingSocialAccountService::class);
        $this->socialAuthenticateService = $this->createMock(UserSocialAuthenticateService::class);
        $this->socialAccountConnectService = $this->createMock(UserSocialAccountConnectService::class);
        $this->normalizer = $this->createMock(SocialUserAttributesNormalizer::class);
    }

    public function testHandleCancelRedirectsToLoginWithoutEnforcingRedirect(): void
    {
        $response = $this->mockPopupAwareRedirectResponse('//voyti/session-login', false);

        $result = $this->createService()->handleCancel($this->client('github'));

        $this->assertSame($response, $result);
    }

    public function testHandleSuccessGuestFailureRendersMessage(): void
    {
        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));
        $this->normalizer->method('normalize')->willReturn($this->attributes());
        $this->socialAuthenticateService->method('run')->willReturn(ServiceResult::failure('could not authenticate'));

        $response = $this->expectMessageRender('could not authenticate');

        $result = $this->createService()->handleSuccess($this->client('github'));

        $this->assertSame($response, $result);
    }

    public function testHandleSuccessGuestRuntimeExceptionRendersMessage(): void
    {
        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));
        $this->normalizer->method('normalize')->willReturn($this->attributes());
        $this->socialAuthenticateService->method('run')->willThrowException(new RuntimeException('state mismatch'));

        $response = $this->expectMessageRender('state mismatch');

        $result = $this->createService()->handleSuccess($this->client('github'));

        $this->assertSame($response, $result);
    }

    public function testHandleSuccessGuestSuccessAddsCookieAndRedirectsHome(): void
    {
        $this->normalizer->method('normalize')->willReturn($this->attributes());
        $this->socialAuthenticateService->method('run')->willReturn(ServiceResult::success());
        $this->pendingSocialAccountService->method('getPendingAccount')->willReturn(null);

        $guestIdentity = $this->createMock(GuestIdentityInterface::class);
        $user = $this->createMock(User::class);
        $this->currentUser->method('getIdentity')->willReturnOnConsecutiveCalls($guestIdentity, $user);
        $this->rememberMeCookieService->method('addCookie')->willReturnArgument(1);

        $response = $this->mockPopupAwareRedirectResponse('//home');

        $result = $this->createService()->handleSuccess($this->client('github'));

        $this->assertSame($response, $result);
    }

    public function testHandleSuccessGuestSuccessRedirectsToPendingConnectWhenAccountPending(): void
    {
        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));
        $this->normalizer->method('normalize')->willReturn($this->attributes());
        $this->socialAuthenticateService->method('run')->willReturn(ServiceResult::success());

        $account = $this->createMock(UserSocialAccount::class);
        $account->method('getCode')->willReturn('pending-code');
        $this->pendingSocialAccountService->method('getPendingAccount')->willReturn($account);

        $response = $this->mockPopupAwareRedirectResponse('//voyti/registration-connect?code=pending-code');

        $result = $this->createService()->handleSuccess($this->client('github'));

        $this->assertSame($response, $result);
    }

    public function testHandleSuccessGuestSuccessWithoutUserIdentityRedirectsHome(): void
    {
        $this->normalizer->method('normalize')->willReturn($this->attributes());
        $this->socialAuthenticateService->method('run')->willReturn(ServiceResult::success());
        $this->pendingSocialAccountService->method('getPendingAccount')->willReturn(null);
        $this->currentUser->method('getIdentity')->willReturn($this->createMock(GuestIdentityInterface::class));

        $response = $this->mockPopupAwareRedirectResponse('//home');

        $result = $this->createService()->handleSuccess($this->client('github'));

        $this->assertSame($response, $result);
    }

    public function testHandleSuccessLoggedInFailureRendersMessage(): void
    {
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $this->normalizer->method('normalize')->willReturn($this->attributes());
        $this->socialAccountConnectService->method('run')->willReturn(ServiceResult::failure('already connected'));

        $response = $this->expectMessageRender('already connected');

        $result = $this->createService()->handleSuccess($this->client('github'));

        $this->assertSame($response, $result);
    }

    public function testHandleSuccessLoggedInRuntimeExceptionRendersMessage(): void
    {
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $this->normalizer->method('normalize')->willReturn($this->attributes());
        $this->socialAccountConnectService->method('run')->willThrowException(new RuntimeException('already connected to another account'));

        $response = $this->expectMessageRender('already connected to another account');

        $result = $this->createService()->handleSuccess($this->client('github'));

        $this->assertSame($response, $result);
    }

    public function testHandleSuccessLoggedInSuccessRedirectsToSocialNetworkIndex(): void
    {
        $identity = $this->createMock(User::class);
        $identity->method('getId')->willReturn('1');
        $this->currentUser->method('getIdentity')->willReturn($identity);

        $this->normalizer->method('normalize')->willReturn($this->attributes());
        $this->socialAccountConnectService->method('run')->willReturn(ServiceResult::success());

        $response = $this->mockPopupAwareRedirectResponse('//voyti/user-social-network');

        $result = $this->createService()->handleSuccess($this->client('github'));

        $this->assertSame($response, $result);
    }

    /**
     * @return array{id: string, email: ?string, username: ?string, name: ?string}
     */
    private function attributes(): array
    {
        return ['id' => 'client123', 'email' => 'user@example.com', 'username' => 'user', 'name' => 'User Name'];
    }

    private function client(string $name): AuthClientInterface&MockObject
    {
        $client = $this->createMock(AuthClientInterface::class);
        $client->method('getName')->willReturn($name);

        return $client;
    }

    private function createService(): SocialAuthCallbackService
    {
        return $this->getTestContainer([
            CurrentUser::class => $this->currentUser,
            FlashInterface::class => $this->flash,
            PendingSocialAccountService::class => $this->pendingSocialAccountService,
            RememberMeCookieService::class => $this->rememberMeCookieService,
            SocialUserAttributesNormalizer::class => $this->normalizer,
            UserSocialAccountConnectService::class => $this->socialAccountConnectService,
            UserSocialAuthenticateService::class => $this->socialAuthenticateService,
            WebViewRenderer::class => $this->viewRenderer,
        ])->get(SocialAuthCallbackService::class);
    }

    private function expectMessageRender(string $expectedTitle): ResponseInterface&MockObject
    {
        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('shared/message', $this->callback(
                static fn(array $params): bool => $params['data']->title === $expectedTitle,
            ))
            ->willReturn($response);

        return $response;
    }

    private function mockPopupAwareRedirectResponse(
        string $expectedUrl,
        bool $expectedEnforceRedirect = true,
    ): ResponseInterface&MockObject {
        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('renderPartial')
            ->with(
                $this->stringEndsWith('/resources/views/redirect.php'),
                ['url' => $expectedUrl, 'enforceRedirect' => $expectedEnforceRedirect, 'appName' => 'app'],
            )
            ->willReturn($response);

        return $response;
    }
}
