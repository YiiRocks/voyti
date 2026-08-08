<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Api\V1\User;

use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\Password\PasswordGeneratorInterface;
use YiiRocks\Voyti\Service\Password\PasswordHistoryService;
use YiiRocks\Voyti\Service\User\UserCreationHelper;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Data\Db\QueryDataReader;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Db\Exception\IntegrityException;
use Yiisoft\Http\Status;
use Yiisoft\Input\Http\Attribute\Parameter\Body;
use Yiisoft\Input\Http\Attribute\Parameter\Query;
use Yiisoft\Router\HydratorAttribute\RouteArgument;
use Yiisoft\Translator\TranslatorInterface;

/**
 * REST CRUD endpoints for users under the `voyti-routes-api` route group, authenticated via
 * {@see ApiTokenAuthenticationMiddleware}. Returns JSON only, no view rendering.
 */
final readonly class UserController
{
    private const int MAX_PER_PAGE = 100;

    public function __construct(
        private TranslatorInterface $translator,
        private VoytiConfig $config,
        private DataResponseFactoryInterface $responseFactory,
        private PasswordGeneratorInterface $passwordGenerator,
        private PasswordHistoryService $passwordHistoryService,
        private UserCreationHelper $userCreationHelper,
    ) {}

    public function create(
        #[Body('email')]
        string $email = '',
        #[Body('username')]
        string $username = '',
        #[Body('password')]
        string $password = '',
    ): ResponseInterface {
        $password = $password !== '' ? $password : $this->passwordGenerator->generate(12);

        $conflict = $this->userCreationHelper->findUniquenessConflict($email, $username);
        if ($conflict !== null) {
            /** @infection-ignore-all Equivalent: removing this early return falls through to save(), which hits the UNIQUE(email|username) constraint and lands in the IntegrityException catch below, producing the byte-identical 400 error response with no user persisted. */
            return $this->responseFactory->createResponse(['error' => $conflict], Status::BAD_REQUEST);
        }

        $user = $this->userCreationHelper->buildUser($email, $username, $password);
        $user->setConfirmedAt(time());
        try {
            $user->save();
            // @codeCoverageIgnoreStart
            // Reachable only under a genuine insert race: findUniquenessConflict() above returned
            // null, yet the save hit a UNIQUE(email|username) constraint because a concurrent request
            // inserted the same value first. That interleaving can't be reproduced single-threaded, so
            // this defensive re-check is excluded from coverage rather than tested via a mocked helper.
        } catch (IntegrityException) {
            $raceConflict = $this->userCreationHelper->findUniquenessConflict($email, $username)
                ?? 'A user with this email or username already exists.';

            return $this->responseFactory->createResponse(['error' => $raceConflict], Status::BAD_REQUEST);
        }
        // @codeCoverageIgnoreEnd
        $this->passwordHistoryService->record($user);

        return $this->responseFactory->createResponse([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'message' => $this->translator->translate('voyti.api.user_created', category: 'voyti'),
        ], Status::CREATED);
    }

    public function delete(#[RouteArgument] int $id): ResponseInterface
    {
        $user = $this->resolveUser($id);
        if (!$user instanceof User) {
            return $user;
        }

        $user->delete();
        return $this->responseFactory->createResponse([
            'message' => $this->translator->translate('voyti.api.user_deleted', category: 'voyti'),
        ]);
    }

    public function index(
        #[Query('username')]
        string $username = '',
        #[Query('email')]
        string $email = '',
        #[Query('status')]
        string $status = '',
        /**
         * @infection-ignore-all Mutating this default to 0 is behaviorally identical to 1: both are
         * floored to 1 by max(1, $page) below, so no test can observe the difference.
         */
        #[Query('page')]
        int $page = 1,
        #[Query('perPage')]
        int $perPage = 25,
    ): ResponseInterface {
        $reader = new QueryDataReader(User::searchQuery([
            'username' => $username,
            'email' => $email,
            'status' => $status,
        ]));

        $pageSize = min(max(1, $perPage), self::MAX_PER_PAGE);
        $sizedPaginator = (new OffsetPaginator($reader))->withPageSize($pageSize);
        $currentPage = min(max(1, $page), max(1, $sizedPaginator->getTotalPages()));
        $paginator = $sizedPaginator->withCurrentPage($currentPage);

        /** @infection-ignore-all — iterator keys are already 0-indexed, preserve_keys has no effect */
        /** @var list<User> $users */
        $users = iterator_to_array($paginator->read(), false);
        $items = array_map(fn(User $u) => [
            'id' => $u->getId(),
            'username' => $u->getUsername(),
            'email' => $u->getEmail(),
            'createdAt' => $u->getCreatedAt(),
            'confirmedAt' => $u->getConfirmedAt(),
            'blockedAt' => $u->getBlockedAt(),
        ], $users);

        return $this->responseFactory->createResponse([
            'items' => $items,
            'totalCount' => $paginator->getTotalItems(),
            'currentPage' => $paginator->getCurrentPage(),
            'pageSize' => $paginator->getPageSize(),
            'totalPages' => $paginator->getTotalPages(),
        ]);
    }

    public function update(
        #[RouteArgument]
        int $id,
        #[Body('password')]
        string $password = '',
        #[Body('username')]
        ?string $username = null,
        #[Body('email')]
        ?string $email = null,
    ): ResponseInterface {
        $user = $this->resolveUser($id);
        if (!$user instanceof User) {
            return $user;
        }

        if ($password !== '' && $this->passwordHistoryService->wasUsedRecently($user, $password)) {
            return $this->responseFactory->createResponse(
                ['error' => $this->translator->translate('voyti.api.password_previously_used', category: 'voyti')],
                Status::BAD_REQUEST,
            );
        }

        if ($username !== null) {
            $user->setUsername($username);
        }
        if ($email !== null) {
            $user->setEmail($email);
        }
        if ($password !== '') {
            $this->passwordHistoryService->applyPasswordChange($user, $password);
        } else {
            $user->setUpdatedAt(time());
            $user->save();
        }

        return $this->responseFactory->createResponse([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'message' => $this->translator->translate('voyti.api.user_updated', category: 'voyti'),
        ]);
    }

    public function view(#[RouteArgument] int $id): ResponseInterface
    {
        $user = $this->resolveUser($id);
        if (!$user instanceof User) {
            return $user;
        }

        return $this->responseFactory->createResponse([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'createdAt' => $user->getCreatedAt(),
        ]);
    }

    private function resolveUser(int $id): User|ResponseInterface
    {
        $user = User::findById($id);
        return $user ?? $this->responseFactory->createResponse(
            ['error' => $this->translator->translate('voyti.api.not_found', category: 'voyti')],
            Status::NOT_FOUND,
        );
    }
}
