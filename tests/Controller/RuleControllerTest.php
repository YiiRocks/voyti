<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller;

use YiiRocks\Voyti\Controller\RuleController;
use YiiRocks\Voyti\Service\Rbac\RuleEditionService;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\Support\FakeAuthRule;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\Validator\Rbac\RuleValidator;
use Yiisoft\Http\Method;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\ValidationContext;
use Yiisoft\Validator\ValidatorInterface;

final class RuleControllerTest extends TestCase
{
    private ControllerHarness $harness;

    protected function setUp(): void
    {
        parent::setUp();

        $this->harness = new ControllerHarness(dirname(__DIR__, 2));
    }

    public function testCreateGetRendersFormWithTranslatedFieldLabels(): void
    {
        $response = $this->harness->ruleController->create($this->harness->request(Method::GET));
        $html = $this->harness->responseBody($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('>Name<', $html);
        $this->assertStringContainsString('>Rule class<', $html);
    }

    public function testCreatePostWithoutParsedBodyShowsInvalidClassError(): void
    {
        $response = $this->harness->ruleController->create($this->harness->request(Method::POST));
        $html = $this->harness->responseBody($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Invalid rule class', $html);
        $this->assertNull($this->harness->flash->get('success'));
    }

    public function testCreateRedirectsWithFlashOnValidRuleClass(): void
    {
        $response = $this->harness->ruleController->create($this->harness->request(
            Method::POST,
            ['rule' => ['name' => 'is-owner', 'class' => FakeAuthRule::class]],
        ));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/voyti/rules', $response->getHeaderLine('Location'));
        $this->assertSame('Authorization rule has been added', $this->harness->flash->get('success'));
    }

    public function testCreateWithInvalidFormRendersErrorsWithoutRedirecting(): void
    {
        $controller = $this->buildRuleControllerWithValidator(
            new RejectingRuleValidatorStub('Name is invalid.'),
        );

        $response = $controller->create($this->harness->request(
            Method::POST,
            ['rule' => ['name' => '', 'class' => FakeAuthRule::class]],
        ));
        $html = $this->harness->responseBody($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Name is invalid.', $html);
        $this->assertNull($this->harness->flash->get('success'));
    }

    public function testDeleteClearsRuleReferencesAndRedirectsWithFlash(): void
    {
        $this->harness->rbacItemsStorage->add((new Role('manager'))->withRuleName('LegacyRuleClass'));
        $this->harness->rbacItemsStorage->add((new Permission('manage-articles'))->withRuleName('LegacyRuleClass'));

        $response = $this->harness->ruleController->delete('LegacyRuleClass');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/voyti/rules', $response->getHeaderLine('Location'));
        $this->assertSame('Authorization rule has been removed', $this->harness->flash->get('success'));
        $this->assertNull($this->harness->rbacItemsStorage->getRole('manager')?->getRuleName());
        $this->assertNull($this->harness->rbacItemsStorage->getPermission('manage-articles')?->getRuleName());
    }

    public function testIndexListsDistinctRuleNames(): void
    {
        $this->harness->rbacItemsStorage->add((new Role('manager'))->withRuleName('IsTeamLeadRule'));
        $this->harness->rbacItemsStorage->add((new Permission('manage-articles'))->withRuleName('IsTeamLeadRule'));
        $this->harness->rbacItemsStorage->add((new Role('editor'))->withRuleName('IsEditorRule'));

        $response = $this->harness->ruleController->index();
        $html = $this->harness->responseBody($response);

        $this->assertStringContainsString('IsTeamLeadRule', $html);
        $this->assertStringContainsString('IsEditorRule', $html);
    }

    public function testUpdateRenamesRuleReferencesAndRedirectsWithFlash(): void
    {
        $this->harness->rbacItemsStorage->add((new Role('manager'))->withRuleName('LegacyRuleClass'));

        $response = $this->harness->ruleController->update(
            $this->harness->request(
                Method::POST,
                ['rule' => ['name' => FakeAuthRule::class, 'class' => FakeAuthRule::class]],
            ),
            'LegacyRuleClass',
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/voyti/rules', $response->getHeaderLine('Location'));
        $this->assertSame('Authorization rule has been updated', $this->harness->flash->get('success'));
        $this->assertSame(FakeAuthRule::class, $this->harness->rbacItemsStorage->getRole('manager')?->getRuleName());
    }

    public function testUpdateWithInvalidFormRendersErrorsWithoutRedirecting(): void
    {
        $controller = $this->buildRuleControllerWithValidator(
            new RejectingRuleValidatorStub('Name is invalid.'),
        );

        $response = $controller->update(
            $this->harness->request(
                Method::POST,
                ['rule' => ['name' => '', 'class' => FakeAuthRule::class]],
            ),
            'LegacyRuleClass',
        );
        $html = $this->harness->responseBody($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Name is invalid.', $html);
        $this->assertNull($this->harness->flash->get('success'));
    }

    public function testUpdateWithInvalidRuleClassShowsError(): void
    {
        $response = $this->harness->ruleController->update(
            $this->harness->request(
                Method::POST,
                ['rule' => ['name' => 'is-owner', 'class' => 'NotARealRuleClass']],
            ),
            'is-owner',
        );
        $html = $this->harness->responseBody($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Invalid rule class', $html);
        $this->assertNull($this->harness->flash->get('success'));
    }

    private function buildRuleControllerWithValidator(ValidatorInterface $validator): RuleController
    {
        return new RuleController(
            $this->harness->translator,
            $this->harness->webViewRenderer,
            $this->harness->authHelper,
            $this->harness->url,
            $validator,
            new RuleEditionService($this->harness->rbacItemsStorage, new RuleValidator()),
            new \Nyholm\Psr7\Factory\Psr17Factory(),
            $this->harness->flash,
        );
    }
}

final class RejectingRuleValidatorStub implements ValidatorInterface
{
    public function __construct(private readonly string $message)
    {
    }

    #[\Override]
    public function validate(
        mixed $data,
        callable|iterable|object|string|null $rules = null,
        ?ValidationContext $context = null,
    ): Result {
        $result = new Result();
        $result->addError($this->message);

        return $result;
    }
}
