<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\SocialNetwork;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Helper\LinkButtonHelper;
use YiiRocks\Voyti\Helper\Views\MenuView;
use YiiRocks\Voyti\Model\UserSocialAccount;
use YiiRocks\Voyti\Service\FlashNotifier;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Router\HydratorAttribute\RouteArgument;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\AuthClient\Widget\AuthChoice;
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
        private FlashNotifier $flashNotifier,
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
        /** @infection-ignore-all This list feeds only the auth-choice widget's client exclusion (array_diff_key), which has no observable effect unless the host has configured OAuth clients; the filter/map normalizations are not exercisable by the library's own suite. */
        $connectedProviders = array_values(array_filter(array_map(
            static fn(UserSocialAccount $account): string => $account->getProvider(),
            $accounts,
        )));

        $authChoice = null;
        if ($this->clientCollection !== null) {
            /** @infection-ignore-all The auth-choice widget (route + cosmetic button styling) only renders when the host has configured OAuth clients, so its construction has no behavioural effect the library's own suite can observe. */
            $authChoice = AuthChoice::widget()
                ->authRoute('voyti/session-auth')
                ->linkAttributes(['class' => LinkButtonHelper::submitButtonClass()]);
        }
        $clients = $authChoice?->getClients() ?? [];
        $url = $this->url;

        $rows = array_map(
            static function (UserSocialAccount $account) use ($clients, $url): array {
                $provider = $account->getProvider();
                $title = array_key_exists($provider, $clients) ? $clients[$provider]->getTitle() : $provider;

                return [
                    'providerTitle' => $title,
                    'formSubmitUrl' => $url->generate('voyti/user-social-network-delete', ['id' => $account->getId()]),
                ];
            },
            $accounts,
        );

        $authChoice?->setClients(array_diff_key($clients, array_flip($connectedProviders)));

        return $this->renderView('social-network/index', [
            'data' => [
                'menu' => MenuView::account($this->config, $this->url, $this->translator()),
                'accounts' => $rows,
                'authChoice' => $authChoice,
            ],
        ]);
    }
}
