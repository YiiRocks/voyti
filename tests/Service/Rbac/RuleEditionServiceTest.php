<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Rbac;

use YiiRocks\Voyti\Form\Rbac\RuleForm;
use YiiRocks\Voyti\Service\Rbac\RuleEditionService;
use YiiRocks\Voyti\tests\Support\FakeAuthRule;
use YiiRocks\Voyti\Validator\Rbac\RuleValidator;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;
use Yiisoft\Rbac\SimpleItemsStorage;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\Message\Php\MessageSource;
use Yiisoft\Translator\SimpleMessageFormatter;
use Yiisoft\Translator\Translator;

final class RuleEditionServiceTest extends \PHPUnit\Framework\TestCase
{
    public function testCreateReturnsFalseForNonExistentClass(): void
    {
        $service = $this->createService(new InMemoryRbacItemsStorage());

        self::assertFalse($service->create($this->makeForm(class: 'NotARealClass')));
    }

    public function testCreateReturnsTrueForValidRuleClass(): void
    {
        $service = $this->createService(new InMemoryRbacItemsStorage());

        self::assertTrue($service->create($this->makeForm(class: FakeAuthRule::class)));
    }

    public function testRemoveClearsRuleNameOnBothRolesAndPermissions(): void
    {
        $itemsStorage = new InMemoryRbacItemsStorage();
        $itemsStorage->add((new Role('manager'))->withRuleName('LegacyRule'));
        $itemsStorage->add((new Permission('manage-articles'))->withRuleName('LegacyRule'));
        $itemsStorage->add((new Role('editor'))->withRuleName('OtherRule'));
        $service = $this->createService($itemsStorage);

        $service->remove('LegacyRule');

        self::assertNull($itemsStorage->getRole('manager')?->getRuleName());
        self::assertNull($itemsStorage->getPermission('manage-articles')?->getRuleName());
        self::assertSame('OtherRule', $itemsStorage->getRole('editor')?->getRuleName());
    }

    public function testUpdateDoesNotRenameWhenPreviousNameIsEmpty(): void
    {
        $itemsStorage = new InMemoryRbacItemsStorage();
        $itemsStorage->add((new Role('manager'))->withRuleName(''));
        $service = $this->createService($itemsStorage);

        $service->update($this->makeForm(previousName: '', class: FakeAuthRule::class));

        self::assertSame('', $itemsStorage->getRole('manager')?->getRuleName());
    }

    public function testUpdateRenamesMatchingItemsWhenPreviousNameDiffersFromClass(): void
    {
        $itemsStorage = new InMemoryRbacItemsStorage();
        $itemsStorage->add((new Role('manager'))->withRuleName('LegacyRule'));
        $service = $this->createService($itemsStorage);

        $result = $service->update($this->makeForm(previousName: 'LegacyRule', class: FakeAuthRule::class));

        self::assertTrue($result);
        self::assertSame(FakeAuthRule::class, $itemsStorage->getRole('manager')?->getRuleName());
    }

    public function testUpdateReturnsFalseWhenNewClassIsInvalid(): void
    {
        $itemsStorage = new InMemoryRbacItemsStorage();
        $itemsStorage->add((new Role('manager'))->withRuleName('LegacyRule'));
        $service = $this->createService($itemsStorage);

        $result = $service->update($this->makeForm(previousName: 'LegacyRule', class: 'NotARealClass'));

        self::assertFalse($result);
    }

    public function testUpdateSkipsRenameWhenPreviousNameEqualsClass(): void
    {
        $itemsStorage = new InMemoryRbacItemsStorage();
        $itemsStorage->add((new Role('manager'))->withRuleName(FakeAuthRule::class));
        $service = $this->createService($itemsStorage);

        $service->update($this->makeForm(previousName: FakeAuthRule::class, class: FakeAuthRule::class));

        self::assertSame(FakeAuthRule::class, $itemsStorage->getRole('manager')?->getRuleName());
    }

    private function createService(InMemoryRbacItemsStorage $itemsStorage): RuleEditionService
    {
        return new RuleEditionService($itemsStorage, new RuleValidator());
    }

    private function makeForm(string $class, string $previousName = ''): RuleForm
    {
        $translator = new Translator('en', null, 'voyti');
        $translator->addCategorySources(
            new CategorySource(
                'voyti',
                new MessageSource(dirname(__DIR__, 3) . '/src/resources/messages'),
                new SimpleMessageFormatter(),
            ),
        );

        $form = new RuleForm($translator);
        $form->previousName = $previousName;
        $form->class = $class;

        return $form;
    }
}

final class InMemoryRbacItemsStorage extends SimpleItemsStorage
{
}
