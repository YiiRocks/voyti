<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Rbac;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use YiiRocks\Voyti\Model\Form\Rbac\RuleForm;
use YiiRocks\Voyti\Service\Rbac\RuleEditionService;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\Validator\Rbac\RuleValidator;
use Yiisoft\Rbac\CompositeRule;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\Role;

#[AllowMockObjectsWithoutExpectations]
final class RuleEditionServiceTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function createProvider(): iterable
    {
        yield 'returns false when invalid' => ['NonExistentClassName', false];
        yield 'returns true when valid' => [CompositeRule::class, true];
    }

    #[DataProvider('createProvider')]
    public function testCreate(string $class, bool $expected): void
    {
        $itemsStorage = $this->createMock(ItemsStorageInterface::class);
        $ruleValidator = new RuleValidator();
        $service = new RuleEditionService($itemsStorage, $ruleValidator);

        $form = $this->createRuleForm(class: $class);
        self::assertSame($expected, $service->create($form));
    }

    public function testRemoveCallsItemsStorageForItemsWithRule(): void
    {
        $roleWithRule = (new Role('editor'))->withRuleName('MyRule');
        $roleWithoutRule = new Role('viewer');
        $itemsStorage = $this->createMock(ItemsStorageInterface::class);
        $itemsStorage->method('getAll')->willReturn([$roleWithRule, $roleWithoutRule]);
        $itemsStorage->expects(self::once())->method('update')->with(
            'editor',
            self::callback(fn(Role $r): bool => $r->getRuleName() === null),
        );
        $ruleValidator = new RuleValidator();
        $service = new RuleEditionService($itemsStorage, $ruleValidator);

        $service->remove('MyRule');
    }

    public function testUpdateDoesNotRenameWhenPreviousNameEmpty(): void
    {
        $roleWithEmptyRule = (new Role('editor'))->withRuleName('');
        $itemsStorage = $this->createMock(ItemsStorageInterface::class);
        $itemsStorage->method('getAll')->willReturn([$roleWithEmptyRule]);
        $itemsStorage->expects(self::never())->method('update');
        $ruleValidator = new RuleValidator();
        $service = new RuleEditionService($itemsStorage, $ruleValidator);

        $form = $this->createRuleForm(class: CompositeRule::class, previousName: '');
        self::assertTrue($service->update($form));
    }

    public function testUpdateRenamesReferences(): void
    {
        $oldRole = (new Role('editor'))->withRuleName('OldRule');
        $itemsStorage = $this->createMock(ItemsStorageInterface::class);
        $itemsStorage->method('getAll')->willReturn([$oldRole]);
        $itemsStorage->expects(self::once())->method('update')->with(
            'editor',
            self::callback(fn(Role $r): bool => $r->getRuleName() === CompositeRule::class),
        );
        $ruleValidator = new RuleValidator();
        $service = new RuleEditionService($itemsStorage, $ruleValidator);

        $form = $this->createRuleForm(class: CompositeRule::class, previousName: 'OldRule');
        self::assertTrue($service->update($form));
    }

    public function testUpdateReturnsTrueWhenPreviousNameDiffers(): void
    {
        $itemsStorage = $this->createMock(ItemsStorageInterface::class);
        $itemsStorage->method('getAll')->willReturn([]);
        $ruleValidator = new RuleValidator();
        $service = new RuleEditionService($itemsStorage, $ruleValidator);

        $form = $this->createRuleForm(class: CompositeRule::class, previousName: 'OldClassName');
        self::assertTrue($service->update($form));
    }

    public function testUpdateReturnsTrueWhenValid(): void
    {
        $itemsStorage = $this->createMock(ItemsStorageInterface::class);
        $ruleValidator = new RuleValidator();
        $service = new RuleEditionService($itemsStorage, $ruleValidator);

        $form = $this->createRuleForm(class: CompositeRule::class);
        self::assertTrue($service->update($form));
    }

    private function createRuleForm(
        string $class = '',
        string $name = '',
        string $previousName = '',
    ): RuleForm {
        $form = new RuleForm($this->createTranslator());
        $form->class = $class;
        $form->name = $name;
        $form->previousName = $previousName;
        return $form;
    }
}
