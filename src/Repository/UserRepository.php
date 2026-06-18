<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Repository;

use Yiisoft\ActiveRecord\ActiveQueryInterface;
use YiiRocks\Voyti\Entity\Profile;
use YiiRocks\Voyti\Entity\Token;
use YiiRocks\Voyti\Entity\User;

final class UserRepository
{
    public function __construct()
    {
    }

    public function findById(int $id): ?User
    {
        return User::query()->findByPk($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::query()->where(['email' => $email])->one();
    }

    public function findByUsername(string $username): ?User
    {
        return User::query()->where(['username' => $username])->one();
    }

    public function findByUsernameOrEmail(string $login): ?User
    {
        return User::query()
            ->where(['or', ['username' => $login], ['email' => $login]])
            ->one();
    }

    public function findAll(): array
    {
        return User::query()->all();
    }

    public function search(array $filters = []): array
    {
        $query = User::query();

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

    public function count(array $filters = []): int
    {
        $query = User::query();

        if (!empty($filters['username'])) {
            $query = $query->where(['like', 'username', $filters['username']]);
        }
        if (!empty($filters['email'])) {
            $query = $query->where(['like', 'email', $filters['email']]);
        }

        return $query->count();
    }

    public function save(User $user): void
    {
        $user->save();
    }

    public function saveWithProfile(User $user, Profile $profile): void
    {
        $user->save();
        $profile->setUserId($user->getId() !== null ? (int) $user->getId() : 0);
        $profile->save();
    }

    public function saveWithProfileAndToken(User $user, Profile $profile, Token $token): void
    {
        $user->save();
        $profile->setUserId($user->getId() !== null ? (int) $user->getId() : 0);
        $profile->save();
        $token->setUserId($user->getId() !== null ? (int) $user->getId() : 0);
        $token->save();
    }

    public function delete(User $user): void
    {
        $profile = $user->getProfile();
        if ($profile !== null) {
            $profile->delete();
        }
        $user->delete();
    }
}
