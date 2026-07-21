<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Profile;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Enum\ProfileVisibility;
use YiiRocks\Voyti\Event\User\UserProfileEvent;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\Helper\InputDataTrait;
use YiiRocks\Voyti\Model\Form\Settings\UserProfileForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\SwitchIdentityService;
use YiiRocks\Voyti\ViewData\Profile\UpdateViewData;
use YiiRocks\Voyti\ViewData\Shared\ProfileCardViewData;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Http\Method;
use Yiisoft\Hydrator\HydratorInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Displays a user's public profile and lets the current user edit their own profile.
 */
final readonly class ProfileController
{
    use InputDataTrait;
    use RedirectTrait;
    use RenderTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private ValidatorInterface $validator,
        private UrlGeneratorInterface $url,
        private AuthHelper $authHelper,
        private ModuleConfig $config,
        private CurrentUser $currentUser,
        private EventDispatcherInterface $eventDispatcher,
        private HydratorInterface $hydrator,
        private ResponseFactoryInterface $responseFactory,
        private FlashInterface $flash,
        private SwitchIdentityService $switchIdentityService,
    ) {}

    public function show(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $identity = $this->currentUser->getIdentity();
        $userId = $identity instanceof IdentityInterface ? $identity->getId() : null;

        $forbidden = match ($this->config->profileVisibility) {
            ProfileVisibility::OWNER => $userId === null || (string) $id !== $userId,
            ProfileVisibility::ADMIN => (string) $id !== $userId && !$this->isAdmin($identity),
            ProfileVisibility::USERS => $userId === null,
            ProfileVisibility::PUBLIC => false,
        };

        if ($forbidden) {
            return $this->renderError('voyti.userProfile.forbidden');
        }

        $userProfile = UserProfile::findByUserId($id);

        if ($userProfile === null) {
            return $this->renderError('voyti.userProfile.not_found');
        }

        $user = User::findById($id);
        // zend.assertions=-1 strips this statement at compile time, so it can never register as executed.
        // @codeCoverageIgnoreStart
        assert($user !== null);
        // @codeCoverageIgnoreEnd

        return $this->renderView('profile/show', [
            'profile' => ProfileCardViewData::create($user, $userProfile, $this->translator()),
        ]);
    }

    public function update(ServerRequestInterface $request): ResponseInterface
    {
        /** @var User $user */
        $user = $this->currentUser->getIdentity();

        $userProfile = $user->getProfile();
        if ($userProfile === null) {
            $userProfile = new UserProfile();
            $userProfile->setUserId((int) $user->getId());
        }

        $form = UserProfileForm::fromProfile($userProfile, $this->translator);

        if ($request->getMethod() === Method::POST) {
            $body = $this->parsedBody($request);
            $this->hydrator->hydrate($form, $this->formData($body, $form->getFormName()));
            $result = $this->validator->validate($form);

            if ($result->isValid()) {
                $form->applyToProfile($userProfile);
                $userProfile->save();
                $this->eventDispatcher->dispatch(new UserProfileEvent($userProfile));
                return $this->redirectWithFlash(
                    $this->url->generate('voyti/user-profile'),
                    'voyti.settings.profile_updated',
                );
            }

            $form->processValidationResult($result);
        }

        return $this->renderView('profile/update', [
            'form' => $form,
            'data' => UpdateViewData::create(
                $user,
                $userProfile,
                $this->config,
                $this->url,
                $this->translator(),
                $this->switchIdentityService->isSwitched(),
                $this->switchIdentityService->getOriginalUser(),
            ),
        ]);
    }

    private function isAdmin(IdentityInterface $identity): bool
    {
        if ($identity instanceof GuestIdentityInterface) {
            return false;
        }
        $id = $identity->getId();
        if ($id === null) {
            return false;
        }
        return $this->authHelper->isAdmin((int) $id);
    }
}
