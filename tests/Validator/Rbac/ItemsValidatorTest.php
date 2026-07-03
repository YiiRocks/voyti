<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Validator\Rbac;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Validator\Rbac\ItemsValidator;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;

final class ItemsValidatorTest extends TestCase
{

    public function testValidateReturnsErrorForEachMissingItem(): void
    {
        $storage = new FakeItemsStorage(['admin']);
        $validator = new ItemsValidator($storage);

        $result = $validator->validate(['admin', 'ghost1', 'ghost2']);

        self::assertSame(['admin', 'ghost1', 'ghost2'], $storage->checkedNames());

        $errors = $result->getErrors();
        self::assertCount(2, $errors);
        self::assertSame("Authorization item 'ghost1' does not exist.", $errors[0]->getMessage());
        self::assertSame("Authorization item 'ghost2' does not exist.", $errors[1]->getMessage());
    }

    public function testValidateReturnsSuccessWhenAllItemsExist(): void
    {
        $storage = new FakeItemsStorage(['admin', 'editor']);
        $validator = new ItemsValidator($storage);

        $result = $validator->validate(['admin', 'editor']);

        self::assertTrue($result->isValid());
        self::assertSame(['admin', 'editor'], $storage->checkedNames());
    }
    public function testValidateReturnsSuccessWhenItemsListIsEmpty(): void
    {
        $storage = new FakeItemsStorage(['admin']);
        $validator = new ItemsValidator($storage);

        $result = $validator->validate([]);

        self::assertTrue($result->isValid());
        self::assertSame([], $storage->checkedNames());
    }
}

final class FakeItemsStorage implements ItemsStorageInterface
{
    /** @var list<string> */
    private array $checked = [];

    /**
     * @param list<string> $existingNames
     */
    public function __construct(
        private readonly array $existingNames,
    ) {
    }

    public function add(Permission|Role $item): void
    {
    }

    public function addChild(string $parentName, string $childName): void
    {
    }

    /**
     * @return list<string>
     */
    public function checkedNames(): array
    {
        return $this->checked;
    }

    public function clear(): void
    {
    }

    public function clearPermissions(): void
    {
    }

    public function clearRoles(): void
    {
    }

    public function exists(string $name): bool
    {
        $this->checked[] = $name;

        return in_array($name, $this->existingNames, true);
    }

    public function get(string $name): Permission|Role|null
    {
        return null;
    }

    public function getAll(): array
    {
        return [];
    }

    public function getAllChildPermissions(array|string $names): array
    {
        return [];
    }

    public function getAllChildren(array|string $names): array
    {
        return [];
    }

    public function getAllChildRoles(array|string $names): array
    {
        return [];
    }

    public function getByNames(array $names): array
    {
        return [];
    }

    public function getDirectChildren(string $name): array
    {
        return [];
    }

    public function getHierarchy(string $name): array
    {
        return [];
    }

    public function getParents(string $name): array
    {
        return [];
    }

    public function getPermission(string $name): ?Permission
    {
        return null;
    }

    public function getPermissions(): array
    {
        return [];
    }

    public function getPermissionsByNames(array $names): array
    {
        return [];
    }

    public function getRole(string $name): ?Role
    {
        return null;
    }

    public function getRoles(): array
    {
        return [];
    }

    public function getRolesByNames(array $names): array
    {
        return [];
    }

    public function hasChild(string $parentName, string $childName): bool
    {
        return false;
    }

    public function hasChildren(string $name): bool
    {
        return false;
    }

    public function hasDirectChild(string $parentName, string $childName): bool
    {
        return false;
    }

    public function remove(string $name): void
    {
    }

    public function removeChild(string $parentName, string $childName): void
    {
    }

    public function removeChildren(string $parentName): void
    {
    }

    public function roleExists(string $name): bool
    {
        return false;
    }

    public function update(string $name, Permission|Role $item): void
    {
    }
}
