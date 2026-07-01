<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service;

use PHPUnit\Framework\TestCase;
use Yiisoft\Translator\TranslatorInterface;
use YiiRocks\Voyti\Service\Rbac\ItemEditionService;
use YiiRocks\Voyti\Validator\Rbac\ItemsValidator;
use YiiRocks\Voyti\Form\Rbac\PermissionForm;
use Yiisoft\Rbac\Manager;
use Yiisoft\Rbac\SimpleItemsStorage;
use Yiisoft\Rbac\SimpleAssignmentsStorage;

final class UpdateTest extends TestCase
{
    public function testPermissionUpdateNoNameChange(): void
    {
        $itemsStorage = new class extends SimpleItemsStorage {};
        $assignmentsStorage = new class extends SimpleAssignmentsStorage {};
        $manager = new Manager($itemsStorage, $assignmentsStorage);
        $itemsValidator = new ItemsValidator($itemsStorage);
        $service = new ItemEditionService($manager, $itemsStorage, $itemsValidator);
        $translator = $this->createStub(TranslatorInterface::class);

        $form = new PermissionForm($translator);
        $form->name = 'test_perm';
        $form->description = 'Original';
        $service->create($form);

        $form2 = new PermissionForm($translator);
        $form2->itemName = 'test_perm';
        $form2->name = 'test_perm';
        $form2->description = 'Updated description';
        $form2->rule = '';
        $form2->children = [];

        $service->update($form2);

        $perm = $itemsStorage->getPermission('test_perm');
        self::assertNotNull($perm);
        self::assertSame('Updated description', $perm->getDescription());
    }

    public function testPermissionUpdateWithNameChange(): void
    {
        $itemsStorage = new class extends SimpleItemsStorage {};
        $assignmentsStorage = new class extends SimpleAssignmentsStorage {};
        $manager = new Manager($itemsStorage, $assignmentsStorage);
        $itemsValidator = new ItemsValidator($itemsStorage);
        $service = new ItemEditionService($manager, $itemsStorage, $itemsValidator);
        $translator = $this->createStub(TranslatorInterface::class);

        $form = new PermissionForm($translator);
        $form->name = 'old_name';
        $form->description = 'Original';
        $service->create($form);

        $form2 = new PermissionForm($translator);
        $form2->itemName = 'old_name';
        $form2->name = 'new_name';
        $form2->description = 'Renamed';
        $form2->rule = '';
        $form2->children = [];

        $service->update($form2);

        $newPerm = $itemsStorage->getPermission('new_name');
        self::assertNotNull($newPerm);
        self::assertSame('Renamed', $newPerm->getDescription());

        $oldPerm = $itemsStorage->getPermission('old_name');
        self::assertNull($oldPerm, 'Old name should no longer exist');
    }

    public function testPermissionUpdateWithSameNameSet(): void
    {
        $itemsStorage = new class extends SimpleItemsStorage {};
        $assignmentsStorage = new class extends SimpleAssignmentsStorage {};
        $manager = new Manager($itemsStorage, $assignmentsStorage);
        $itemsValidator = new ItemsValidator($itemsStorage);
        $service = new ItemEditionService($manager, $itemsStorage, $itemsValidator);
        $translator = $this->createStub(TranslatorInterface::class);

        $form = new PermissionForm($translator);
        $form->name = 'my_perm';
        $form->description = 'Original';
        $service->create($form);

        $form2 = new PermissionForm($translator);
        $form2->itemName = 'my_perm';
        $form2->name = 'my_perm';
        $form2->description = 'New desc';
        $form2->rule = '';
        $form2->children = [];

        $service->update($form2);

        $perm = $itemsStorage->getPermission('my_perm');
        self::assertNotNull($perm);
        self::assertSame('New desc', $perm->getDescription());
    }

    public function testPermissionUpdateWithChildren(): void
    {
        $itemsStorage = new class extends SimpleItemsStorage {};
        $assignmentsStorage = new class extends SimpleAssignmentsStorage {};
        $manager = new Manager($itemsStorage, $assignmentsStorage);
        $itemsValidator = new ItemsValidator($itemsStorage);
        $service = new ItemEditionService($manager, $itemsStorage, $itemsValidator);
        $translator = $this->createStub(TranslatorInterface::class);

        $childForm = new PermissionForm($translator);
        $childForm->name = 'child_perm';
        $childForm->description = 'Child';
        $service->create($childForm);

        $parentForm = new PermissionForm($translator);
        $parentForm->name = 'parent_perm';
        $parentForm->description = 'Parent';
        $service->create($parentForm);

        $updateForm = new PermissionForm($translator);
        $updateForm->itemName = 'parent_perm';
        $updateForm->name = 'parent_perm';
        $updateForm->description = 'Parent with child';
        $updateForm->rule = '';
        $updateForm->children = ['child_perm'];

        $service->update($updateForm);

        self::assertTrue($manager->hasChild('parent_perm', 'child_perm'));

        $renameForm = new PermissionForm($translator);
        $renameForm->itemName = 'parent_perm';
        $renameForm->name = 'renamed_parent';
        $renameForm->description = 'Renamed parent';
        $renameForm->rule = '';
        $renameForm->children = ['child_perm'];

        $service->update($renameForm);

        self::assertTrue($manager->hasChild('renamed_parent', 'child_perm'),
            'Children should be preserved after rename');
    }

    public function testPermissionCreateThenDescribeThenUpdate(): void
    {
        $itemsStorage = new class extends SimpleItemsStorage {};
        $assignmentsStorage = new class extends SimpleAssignmentsStorage {};
        $manager = new Manager($itemsStorage, $assignmentsStorage);
        $itemsValidator = new ItemsValidator($itemsStorage);
        $service = new ItemEditionService($manager, $itemsStorage, $itemsValidator);
        $translator = $this->createStub(TranslatorInterface::class);

        $form = new PermissionForm($translator);
        $form->name = 'perm';
        $form->description = '';
        $service->create($form);

        $perm = $itemsStorage->getPermission('perm');
        self::assertSame('', $perm->getDescription());

        $form2 = new PermissionForm($translator);
        $form2->itemName = 'perm';
        $form2->name = 'perm';
        $form2->description = 'Now has description';
        $form2->rule = '';
        $form2->children = [];

        $service->update($form2);

        $perm = $itemsStorage->getPermission('perm');
        self::assertSame('Now has description', $perm->getDescription());
    }
}
