<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Repository;

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserProfile;
use YiiRocks\Voyti\Entity\UserToken;
use Yiisoft\ActiveRecord\ActiveRecordInterface;

/** @extends BaseRepository<User> */
final class UserRepository extends BaseRepository
{
    /**
     * @psalm-return int<0, max>|string
     */
    public function countByFilters(array $filters = []): int|string
    {
        $query = $this->query(User::class);

        if (!empty($filters['username'])) {
            $query = $query->andWhere(['like', 'username', $filters['username']]);
        }
        if (!empty($filters['email'])) {
            $query = $query->andWhere(['like', 'email', $filters['email']]);
        }

        return $query->count();
    }

    #[\Override]
    public function delete(ActiveRecordInterface $model): void
    {
        if ($model instanceof User) {
            $userProfile = $model->getProfile();
            if ($userProfile !== null) {
                $userProfile->delete();
            }
        }
        parent::delete($model);
    }

    /**
     * @psalm-return list<User>
     */
    public function findAllUsers(): array
    {
        return $this->findAll(User::class);
    }

    public function findByEmail(string $email): ?User
    {
        /** @var ?User $user */
        $user = $this->findOne(User::class, ['email' => $email]);
        return $user;
    }

    public function findById(int $id): ?User
    {
        /** @var ?User $user */
        $user = User::query()->findByPk($id);
        return $user;
    }

    /**
     * @param list<int> $ids
     *
     * @psalm-return list<User>
     */
    public function findByIds(array $ids): array
    {
        return $this->findAll(User::class, ['id' => $ids]);
    }

    public function findByUsername(string $username): ?User
    {
        /** @var ?User $user */
        $user = $this->findOne(User::class, ['username' => $username]);
        return $user;
    }

    public function findByUsernameOrEmail(string $login): ?User
    {
        /** @var ?User $user */
        $user = $this->findOne(User::class, ['or', ['username' => $login], ['email' => $login]]);
        return $user;
    }

    #[\Override]
    public function save(ActiveRecordInterface $model): void
    {
        parent::save($model);
    }

    public function saveWithProfile(User $user, UserProfile $userProfile): void
    {
        $this->save($user);
        $userProfile->setUserId($user->getId() !== null ? (int) $user->getId() : 0);
        $this->save($userProfile);
    }

    public function saveWithProfileAndToken(User $user, UserProfile $userProfile, UserToken $userToken): void
    {
        $this->save($user);
        $userProfile->setUserId($user->getId() !== null ? (int) $user->getId() : 0);
        $this->save($userProfile);
        $userToken->setUserId($user->getId() !== null ? (int) $user->getId() : 0);
        $this->save($userToken);
    }

    /**
     * @return (array|object)[]
     *
     * @psalm-return array<array|object>
     */
    public function search(array $filters = []): array
    {
        $query = $this->query(User::class);

        if (!empty($filters['username'])) {
            $query = $query->andWhere(['like', 'username', $filters['username']]);
        }
        if (!empty($filters['email'])) {
            $query = $query->andWhere(['like', 'email', $filters['email']]);
        }
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'blocked') {
                $query = $query->andWhere(['not', ['blocked_at' => null]]);
            } elseif ($filters['status'] === 'confirmed') {
                $query = $query->andWhere(['not', ['confirmed_at' => null]]);
            } elseif ($filters['status'] === 'unconfirmed') {
                $query = $query->andWhere(['confirmed_at' => null]);
            }
        }

        $limit = (int)($filters['limit'] ?? 50);
        /** @infection-ignore-all DecrementInteger: the surrounding max(1, ...) already clamps a missing 'page' key to 1 regardless of whether the coalesce default here is 1 or 0, so that specific mutation is unobservable. */
        $page = max(1, (int)($filters['page'] ?? 1));
        $offset = ($page - 1) * $limit;

        return $query->limit($limit)->offset($offset)->all();
    }
}
