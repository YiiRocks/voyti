<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Admin\Rbac\Rule;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use YiiRocks\Voyti\Controller\Admin\Rbac\Rule\RuleController;
use YiiRocks\Voyti\Model\UserAuditLog;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\SimpleItemsStorage;
use YiiRocks\Voyti\tests\Support\TestContainerTrait;
use Yiisoft\Rbac\CompositeRule;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\Role;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\ValidatorInterface;

#[AllowMockObjectsWithoutExpectations]
final class RuleControllerTest extends DatabaseTestCase
{
    use TestContainerTrait;

    private FlashInterface&MockObject $flash;
    private SimpleItemsStorage $itemsStorage;
    private ValidatorInterface&MockObject $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->flash = $this->createMock(FlashInterface::class);
        $this->itemsStorage = new SimpleItemsStorage();
    }

    public function testCreateGetShowsForm(): void
    {
        $html = (string) $this->createController()->create(request: new ServerRequest('GET', '/'))->getBody();
        self::assertStringContainsString('Create rule', $html);
    }

    public function testCreatePostErrors(): void
    {
        // Service fails: invalid class
        $this->validator->method('validate')->willReturn(new Result());
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['rule' => ['name' => 'badRule', 'class' => 'Invalid\\Class']]);
        $html = (string) $this->createController()->create(request: $request)->getBody();
        self::assertStringContainsString('Invalid rule class', $html);

        // Validation fails: missing required field
        $validator2 = $this->createMock(ValidatorInterface::class);
        $validationResult = new Result();
        $validationResult->addError('Name is required.');
        $validator2->method('validate')->willReturn($validationResult);
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['rule' => ['name' => '', 'class' => '']]);
        $html = (string) $this->getTestContainer([
            FlashInterface::class => $this->flash,
            ItemsStorageInterface::class => $this->itemsStorage,
            ValidatorInterface::class => $validator2,
        ])->get(RuleController::class)->create(request: $request)->getBody();
        self::assertStringContainsString('Name is required.', $html);
    }

    public function testCreatePostSuccessful(): void
    {
        $this->validator->method('validate')->willReturn(new Result());
        $request = new ServerRequest('POST', '/');
        $result = $this->createController()->create(request: $request, formData: ['name' => 'myRule', 'class' => CompositeRule::class]);
        $this->assertSame(302, $result->getStatusCode());
        $logs = UserAuditLog::search(['action' => 'rbac.rule.create'])->all();
        self::assertNotEmpty($logs);
        self::assertSame('myRule', $logs[0]->getTargetName());
    }

    public function testDelete(): void
    {
        $this->itemsStorage->add((new Role('editor'))->withRuleName('myRule'));
        $result = $this->createController()->delete(new ServerRequest('POST', '/'), 'myRule');
        $this->assertSame(302, $result->getStatusCode());
        $this->assertNull($this->itemsStorage->getRole('editor')?->getRuleName());
        $logs = UserAuditLog::search(['action' => 'rbac.rule.delete'])->all();
        self::assertNotEmpty($logs);
        self::assertSame('myRule', $logs[0]->getTargetName());
    }

    public function testIndex(): void
    {
        $html = (string) $this->createController()->index()->getBody();
        self::assertStringContainsString('Rules', $html);
        self::assertStringContainsString('Create rule', $html);
    }

    public function testUpdateGetShowsForm(): void
    {
        $html = (string) $this->createController()->update(request: new ServerRequest('GET', '/'), name: 'existingRule')->getBody();
        self::assertStringContainsString('Update rule', $html);
    }

    public function testUpdatePostErrors(): void
    {
        // Service fails: invalid class
        $this->validator->method('validate')->willReturn(new Result());
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['rule' => ['name' => 'badRule', 'class' => 'Invalid\\Class']]);
        $html = (string) $this->createController()->update(request: $request, name: 'oldRule')->getBody();
        self::assertStringContainsString('Invalid rule class', $html);

        // Validation fails: missing required field
        $validator2 = $this->createMock(ValidatorInterface::class);
        $validationResult = new Result();
        $validationResult->addError('Name is required.');
        $validator2->method('validate')->willReturn($validationResult);
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['rule' => ['name' => '', 'class' => '']]);
        $html = (string) $this->getTestContainer([
            FlashInterface::class => $this->flash,
            ItemsStorageInterface::class => $this->itemsStorage,
            ValidatorInterface::class => $validator2,
        ])->get(RuleController::class)->update(request: $request, name: 'oldRule')->getBody();
        self::assertStringContainsString('Name is required.', $html);
    }

    public function testUpdatePostSuccessful(): void
    {
        $this->validator->method('validate')->willReturn(new Result());
        $request = new ServerRequest('POST', '/');
        $result = $this->createController()->update(request: $request, name: 'oldRule', formData: ['name' => 'updatedRule', 'class' => CompositeRule::class]);
        $this->assertSame(302, $result->getStatusCode());
        $logs = UserAuditLog::search(['action' => 'rbac.rule.update'])->all();
        self::assertNotEmpty($logs);
        self::assertSame('updatedRule', $logs[0]->getTargetName());
    }

    private function createController(): RuleController
    {
        return $this->getTestContainer([
            FlashInterface::class => $this->flash,
            ItemsStorageInterface::class => $this->itemsStorage,
            ValidatorInterface::class => $this->validator,
        ])->get(RuleController::class);
    }
}
