<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Repository;

use YiiRocks\Voyti\Entity\Profile;
use YiiRocks\Voyti\Entity\Token;
use YiiRocks\Voyti\Entity\User;

final class UserRepository extends BaseRepository
{
    public function __construct()
    {
    }

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
    public function delete(object|array $model): void
    {
        if ($model instanceof User) {
            $profile = $model->getProfile();
            if ($profile !== null) {
                $profile->delete();
            }
        }
        parent::delete($model);
    }

    public function findAllUsers(): array
    {
        return $this->findAll(User::class);
    }

    public function findByEmail(string $email): array|\Yiisoft\ActiveRecord\ActiveRecordInterface|null
    {
        return $this->findOne(User::class, ['email' => $email]);
    }

    public function findById(int $id): array|\Yiisoft\ActiveRecord\ActiveRecordInterface|null
    {
        return User::query()->findByPk($id);
    }

    public function findByUsername(string $username): array|\Yiisoft\ActiveRecord\ActiveRecordInterface|null
    {
        return $this->findOne(User::class, ['username' => $username]);
    }

    public function findByUsernameOrEmail(string $login): array|\Yiisoft\ActiveRecord\ActiveRecordInterface|null
    {
        return $this->findOne(User::class, ['or', ['username' => $login], ['email' => $login]]);
    }

    #[\Override]
    public function save(object|array $model): bool
    {
        return parent::save($model);
    }

    public function saveWithProfile(User $user, Profile $profile): void
    {
        $this->save($user);
        $profile->setUserId($user->getId() !== null ? (int) $user->getId() : 0);
        $this->save($profile);
    }

    public function saveWithProfileAndToken(User $user, Profile $profile, Token $token): void
    {
        $this->save($user);
        $profile->setUserId($user->getId() !== null ? (int) $user->getId() : 0);
        $this->save($profile);
        $token->setUserId($user->getId() !== null ? (int) $user->getId() : 0);
        $this->save($token);
    }

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
