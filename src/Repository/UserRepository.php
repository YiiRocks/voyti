<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Repository;

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserProfile;
use YiiRocks\Voyti\Entity\UserToken;
use Yiisoft\ActiveRecord\ActiveRecordInterface;

final class UserRepository
{
    /**
     * @psalm-return int<0, max>|string
     */
    public function countByFilters(array $filters = []): int|string
    {
        $query = User::query();

        if (!empty($filters['username'])) {
            $query = $query->andWhere(['like', 'username', $filters['username']]);
        }
        if (!empty($filters['email'])) {
            $query = $query->andWhere(['like', 'email', $filters['email']]);
        }

        return $query->count();
    }

    public function delete(ActiveRecordInterface $model): void
    {
        if ($model instanceof User) {
            $userProfile = $model->getProfile();
            if ($userProfile !== null) {
                $userProfile->delete();
            }
        }
        $model->delete();
    }

    /**
     * @psalm-return list<User>
     */
    public function findAllUsers(): array
    {
        /** @var list<User> $users */
        $users = User::query()->all();
        return $users;
    }

    public function findByEmail(string $email): ?User
    {
        /** @var ?User $user */
        $user = User::query()->where(['email' => $email])->one();
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
        /** @var list<User> $users */
        $users = User::query()->where(['id' => $ids])->all();
        return $users;
    }

    public function findByUsername(string $username): ?User
    {
        /** @var ?User $user */
        $user = User::query()->where(['username' => $username])->one();
        return $user;
    }

    public function findByUsernameOrEmail(string $login): ?User
    {
        /** @var ?User $user */
        $user = User::query()->where(['or', ['username' => $login], ['email' => $login]])->one();
        return $user;
    }

    public function save(ActiveRecordInterface $model): void
    {
        $model->save();
    }

    public function saveWithProfile(User $user, UserProfile $userProfile): void
    {
        $this->save($user);
        $userProfile->setUserId($user->getIdOrZero());
        $this->save($userProfile);
    }

    public function saveWithProfileAndToken(User $user, UserProfile $userProfile, UserToken $userToken): void
    {
        $this->save($user);
        $userProfile->setUserId($user->getIdOrZero());
        $this->save($userProfile);
        $userToken->setUserId($user->getIdOrZero());
        $this->save($userToken);
    }

    /**
     * @return (array|object)[]
     *
     * @psalm-return array<array|object>
     */
    public function search(array $filters = []): array
    {
        $query = User::query();

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
        /** @infection-ignore-all DecrementInteger: the surrounding max(1, ...) already clamps a missing 'page' key to 1 regardless of whether the coalesce default is 1 or 0, so that specific mutation is unobservable. */
        $page = max(1, (int)($filters['page'] ?? 1));
        $offset = ($page - 1) * $limit;

        return $query->limit($limit)->offset($offset)->all();
    }
}
