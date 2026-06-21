<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Repository;

use YiiRocks\Voyti\Entity\UserProfile;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Entity\User;

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
            $query = $query->where(['like', 'username', $filters['username']]);
        }
        if (!empty($filters['email'])) {
            $query = $query->where(['like', 'email', $filters['email']]);
        }

        return $query->count();
    }

    #[\Override]
    public function delete(\Yiisoft\ActiveRecord\ActiveRecordInterface $model): void
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
     * @return User[]
     */
    public function findAllUsers(): array
    {
        return $this->findAll(User::class);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOne(User::class, ['email' => $email]);
    }

    public function findById(int $id): ?User
    {
        return User::query()->findByPk($id);
    }

    public function findByUsername(string $username): ?User
    {
        return $this->findOne(User::class, ['username' => $username]);
    }

    public function findByUsernameOrEmail(string $login): ?User
    {
        return $this->findOne(User::class, ['or', ['username' => $login], ['email' => $login]]);
    }

    #[\Override]
    public function save(\Yiisoft\ActiveRecord\ActiveRecordInterface $model): bool
    {
        return parent::save($model);
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
            $query = $query->where(['like', 'username', $filters['username']]);
        }
        if (!empty($filters['email'])) {
            $query = $query->where(['like', 'email', $filters['email']]);
        }
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'blocked') {
                $query = $query->where(['not', ['blocked_at' => null]]);
            } elseif ($filters['status'] === 'confirmed') {
                $query = $query->where(['not', ['confirmed_at' => null]]);
            } elseif ($filters['status'] === 'unconfirmed') {
                $query = $query->where(['confirmed_at' => null]);
            }
        }

        $limit = (int)($filters['limit'] ?? 50);
        $page = max(1, (int)($filters['page'] ?? 1));
        $offset = ($page - 1) * $limit;

        return $query->limit($limit)->offset($offset)->all();
    }
}
