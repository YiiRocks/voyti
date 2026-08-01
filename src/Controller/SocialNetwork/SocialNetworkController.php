<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\SocialNetwork;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Model\UserSocialAccount;
use YiiRocks\Voyti\Service\SwitchIdentityService;
use YiiRocks\Voyti\ViewData\SocialNetwork\IndexViewData;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Router\HydratorAttribute\RouteArgument;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Lists the current user's connected social accounts and lets them disconnect one.
 */
final readonly class SocialNetworkController
{
    use RedirectTrait;
    use RenderTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private UrlGeneratorInterface $url,
        private VoytiConfig $config,
        private ?Collection $clientCollection,
        private CurrentUser $currentUser,
        private ResponseFactoryInterface $responseFactory,
        private FlashInterface $flash,
        private SwitchIdentityService $switchIdentityService,
    ) {}

    public function delete(#[RouteArgument] int $id): ResponseInterface
    {
        $user = $this->currentUser->getIdentity();
        /** @var ?UserSocialAccount $account */
        $account = UserSocialAccount::query()->where(['id' => $id])->one();

        if ($account !== null && $account->getUserId() === (int) $user->getId()) {
            $account->delete();
            return $this->redirectWithFlash(
                $this->url->generate('voyti/user-social-network'),
                'voyti.settings.network_disconnected',
            );
        }

        return $this->renderError('voyti.settings.network_not_found');
    }

    public function index(): ResponseInterface
    {
        $user = $this->currentUser->getIdentity();
        $accounts = UserSocialAccount::findByUserId((int) $user->getId());
        $connectedProviders = array_filter(array_map(
            static fn(UserSocialAccount $account): string => $account->getProvider(),
            $accounts,
        ));

        return $this->renderView('social-network/index', [
            'data' => IndexViewData::create(
                $accounts,
                $this->clientCollection,
                array_values($connectedProviders),
                $this->config,
                $this->url,
                $this->translator(),
                $this->switchIdentityService->isSwitched(),
                $this->switchIdentityService->getOriginalUser(),
            ),
        ]);
    }
}
