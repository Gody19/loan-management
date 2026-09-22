<?php

namespace Tests\Feature\Finance;

use App\Enums\CollateralType;
use App\Enums\LoanCollateralStatus;
use App\Models\LoanApplication;
use App\Models\LoanApplicationCollateral;
use App\Models\LoanPlan;
use App\Models\LoanPlanCollateralRule;
use App\Models\Organization;
use App\Models\User;
use App\Services\CollateralRequirementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollateralRuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->admin = User::where('email', 'admin@financepro.co.tz')->first();
        $this->organization = Organization::factory()->create();
    }

    private function makePlan(array $overrides = []): LoanPlan
    {
        return LoanPlan::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'requires_collateral' => true,
        ], $overrides));
    }

    private function makeRule(LoanPlan $plan, array $overrides = []): LoanPlanCollateralRule
    {
        return LoanPlanCollateralRule::create(array_merge([
            'loan_plan_id' => $plan->id,
            'minimum_amount' => 0,
            'maximum_amount' => 10000000,
            'collateral_required' => true,
            'coverage_percentage' => 150,
            'minimum_collateral_value' => 500000,
            'minimum_assets' => 1,
            'maximum_assets' => 3,
            'allowed_collateral_types' => ['land', 'vehicle', 'building'],
            'required_document_types' => ['ownership_document'],
            'description' => 'Standard rule',
            'status' => true,
        ], $overrides));
    }

    private function makeApplication(LoanPlan $plan, float $amount = 1000000): LoanApplication
    {
        $member = \App\Models\Member::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        return LoanApplication::factory()->create([
            'organization_id' => $this->organization->id,
            'loan_plan_id' => $plan->id,
            'member_id' => $member->id,
            'requested_amount' => $amount,
        ]);
    }

    // ---------------------------------------------------------------
    // LoanPlanCollateralRule Model Tests
    // ---------------------------------------------------------------

    public function test_rule_belongs_to_loan_plan(): void
    {
        $plan = $this->makePlan();
        $rule = $this->makeRule($plan);

        $this->assertInstanceOf(LoanPlan::class, $rule->loanPlan);
        $this->assertEquals($plan->id, $rule->loanPlan->id);
    }

    public function test_rule_active_scope(): void
    {
        $plan = $this->makePlan();
        $active = $this->makeRule($plan, ['status' => true]);
        $inactive = $this->makeRule($plan, ['status' => false, 'minimum_amount' => 99999999, 'maximum_amount' => 999999999]);

        $this->assertEquals(1, LoanPlanCollateralRule::active()->count());
        $this->assertTrue(LoanPlanCollateralRule::active()->first()->id === $active->id);
    }

    public function test_rule_for_amount_scope(): void
    {
        $plan = $this->makePlan();
        $this->makeRule($plan, ['minimum_amount' => 100000, 'maximum_amount' => 500000]);
        $this->makeRule($plan, ['minimum_amount' => 500001, 'maximum_amount' => 1000000, 'minimum_amount' => 500001]);

        $this->assertEquals(1, LoanPlanCollateralRule::forAmount(300000)->count());
        $this->assertEquals(1, LoanPlanCollateralRule::forAmount(750000)->count());
        $this->assertEquals(0, LoanPlanCollateralRule::forAmount(50)->count());
    }

    public function test_rule_allows_collateral_type(): void
    {
        $plan = $this->makePlan();
        $rule = $this->makeRule($plan, ['allowed_collateral_types' => ['land', 'vehicle']]);

        $this->assertTrue($rule->allowsCollateralType('land'));
        $this->assertTrue($rule->allowsCollateralType('vehicle'));
        $this->assertFalse($rule->allowsCollateralType('jewelry'));
    }

    public function test_rule_allows_all_types_when_null(): void
    {
        $plan = $this->makePlan();
        $rule = $this->makeRule($plan, ['allowed_collateral_types' => null]);

        $this->assertTrue($rule->allowsCollateralType('land'));
        $this->assertTrue($rule->allowsCollateralType('jewelry'));
    }

    public function test_rule_requires_document_type(): void
    {
        $plan = $this->makePlan();
        $rule = $this->makeRule($plan, ['required_document_types' => ['ownership_document', 'valuation_report']]);

        $this->assertTrue($rule->requiresDocumentType('ownership_document'));
        $this->assertTrue($rule->requiresDocumentType('valuation_report'));
        $this->assertFalse($rule->requiresDocumentType('photographs'));
    }

    public function test_rule_no_documents_when_null(): void
    {
        $plan = $this->makePlan();
        $rule = $this->makeRule($plan, ['required_document_types' => null]);

        $this->assertFalse($rule->requiresDocumentType('ownership_document'));
    }

    public function test_calculate_minimum_collateral_value_uses_coverage(): void
    {
        $plan = $this->makePlan();
        $rule = $this->makeRule($plan, ['coverage_percentage' => 150, 'minimum_collateral_value' => 500000]);

        $result = $rule->calculateMinimumCollateralValue(1000000);
        $this->assertEquals(1500000.0, $result);
    }

    public function test_calculate_minimum_collateral_value_floors_at_minimum(): void
    {
        $plan = $this->makePlan();
        $rule = $this->makeRule($plan, ['coverage_percentage' => 50, 'minimum_collateral_value' => 1000000]);

        $result = $rule->calculateMinimumCollateralValue(500000);
        $this->assertEquals(1000000.0, $result);
    }

    // ---------------------------------------------------------------
    // CollateralRequirementService Tests
    // ---------------------------------------------------------------

    public function test_resolve_rule_returns_matching_rule(): void
    {
        $service = app(CollateralRequirementService::class);
        $plan = $this->makePlan();
        $this->makeRule($plan, ['minimum_amount' => 100000, 'maximum_amount' => 1000000]);

        $rule = $service->resolveRule($plan, 500000);
        $this->assertNotNull($rule);
        $this->assertEquals(100000, (float) $rule->minimum_amount);
    }

    public function test_resolve_rule_returns_null_when_no_match(): void
    {
        $service = app(CollateralRequirementService::class);
        $plan = $this->makePlan();
        $this->makeRule($plan, ['minimum_amount' => 5000000, 'maximum_amount' => 10000000]);

        $rule = $service->resolveRule($plan, 100000);
        $this->assertNull($rule);
    }

    public function test_is_collateral_required_true(): void
    {
        $service = app(CollateralRequirementService::class);
        $plan = $this->makePlan();
        $this->makeRule($plan, ['collateral_required' => true]);

        $this->assertTrue($service->isCollateralRequired($plan, 500000));
    }

    public function test_is_collateral_required_false_when_not_required(): void
    {
        $service = app(CollateralRequirementService::class);
        $plan = $this->makePlan();
        $this->makeRule($plan, ['collateral_required' => false]);

        $this->assertFalse($service->isCollateralRequired($plan, 500000));
    }

    public function test_is_collateral_required_false_when_no_rule(): void
    {
        $service = app(CollateralRequirementService::class);
        $plan = $this->makePlan();

        $this->assertFalse($service->isCollateralRequired($plan, 500000));
    }

    public function test_calculate_required_value(): void
    {
        $service = app(CollateralRequirementService::class);
        $plan = $this->makePlan();
        $this->makeRule($plan, ['coverage_percentage' => 200, 'minimum_collateral_value' => 100000]);

        $value = $service->calculateRequiredValue($plan, 1000000);
        $this->assertEquals(2000000.0, $value);
    }

    public function test_calculate_required_value_zero_when_no_rule(): void
    {
        $service = app(CollateralRequirementService::class);
        $plan = $this->makePlan();

        $this->assertEquals(0, $service->calculateRequiredValue($plan, 500000));
    }

    public function test_get_requirement_returns_full_details(): void
    {
        $service = app(CollateralRequirementService::class);
        $plan = $this->makePlan();
        $rule = $this->makeRule($plan);

        $req = $service->getRequirement($plan, 500000);
        $this->assertTrue($req['required']);
        $this->assertNotNull($req['rule']);
        $this->assertGreaterThan(0, $req['minimum_value']);
        $this->assertEquals(150.0, $req['coverage_percentage']);
    }

    public function test_get_requirement_not_required(): void
    {
        $service = app(CollateralRequirementService::class);
        $plan = $this->makePlan();
        $this->makeRule($plan, ['collateral_required' => false]);

        $req = $service->getRequirement($plan, 500000);
        $this->assertFalse($req['required']);
        $this->assertEquals(0, $req['minimum_value']);
    }

    // ---------------------------------------------------------------
    // Validate Collateral Tests
    // ---------------------------------------------------------------

    public function test_validate_collateral_passes_when_no_rule(): void
    {
        $service = app(CollateralRequirementService::class);
        $plan = $this->makePlan(['requires_collateral' => false]);
        $app = $this->makeApplication($plan);

        $errors = $service->validateCollateral($app);
        $this->assertEmpty($errors);
    }

    public function test_validate_collateral_passes_when_not_required(): void
    {
        $service = app(CollateralRequirementService::class);
        $plan = $this->makePlan();
        $this->makeRule($plan, ['collateral_required' => false]);
        $app = $this->makeApplication($plan);

        $errors = $service->validateCollateral($app);
        $this->assertEmpty($errors);
    }

    public function test_validate_collateral_fails_minimum_assets(): void
    {
        $service = app(CollateralRequirementService::class);
        $plan = $this->makePlan();
        $this->makeRule($plan, ['minimum_assets' => 2]);
        $app = $this->makeApplication($plan);

        LoanApplicationCollateral::factory()->create([
            'loan_application_id' => $app->id,
            'estimated_value' => 1000000,
        ]);

        $errors = $service->validateCollateral($app);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Minimum 2 collateral asset(s) required', $errors[0]);
    }

    public function test_validate_collateral_fails_maximum_assets(): void
    {
        $service = app(CollateralRequirementService::class);
        $plan = $this->makePlan();
        $this->makeRule($plan, ['maximum_assets' => 2]);
        $app = $this->makeApplication($plan);

        LoanApplicationCollateral::factory()->create(['loan_application_id' => $app->id, 'estimated_value' => 500000]);
        LoanApplicationCollateral::factory()->create(['loan_application_id' => $app->id, 'estimated_value' => 500000]);
        LoanApplicationCollateral::factory()->create(['loan_application_id' => $app->id, 'estimated_value' => 500000]);

        $errors = $service->validateCollateral($app);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Maximum 2 collateral asset(s) allowed', $errors[0]);
    }

    public function test_validate_collateral_fails_disallowed_type(): void
    {
        $service = app(CollateralRequirementService::class);
        $plan = $this->makePlan();
        $this->makeRule($plan, ['allowed_collateral_types' => ['land']]);
        $app = $this->makeApplication($plan);

        LoanApplicationCollateral::factory()->create([
            'loan_application_id' => $app->id,
            'collateral_type' => \App\Enums\CollateralType::Jewelry,
            'estimated_value' => 1000000,
        ]);

        $errors = $service->validateCollateral($app);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('not allowed', $errors[0]);
    }

    public function test_validate_collateral_fails_insufficient_value(): void
    {
        $service = app(CollateralRequirementService::class);
        $plan = $this->makePlan();
        $this->makeRule($plan, [
            'coverage_percentage' => 150,
            'minimum_collateral_value' => 5000000,
            'required_document_types' => [],
        ]);
        $app = $this->makeApplication($plan, 2000000);

        LoanApplicationCollateral::factory()->create([
            'loan_application_id' => $app->id,
            'estimated_value' => 1000000,
        ]);

        $errors = $service->validateCollateral($app);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Insufficient collateral value', $errors[0]);
    }

    public function test_validate_collateral_passes_with_valid_collateral(): void
    {
        $service = app(CollateralRequirementService::class);
        $plan = $this->makePlan();
        $this->makeRule($plan, [
            'coverage_percentage' => 100,
            'minimum_collateral_value' => 500000,
            'minimum_assets' => 1,
            'maximum_assets' => 3,
            'allowed_collateral_types' => ['land'],
            'required_document_types' => [],
        ]);
        $app = $this->makeApplication($plan, 1000000);

        LoanApplicationCollateral::factory()->create([
            'loan_application_id' => $app->id,
            'collateral_type' => \App\Enums\CollateralType::Land,
            'estimated_value' => 1500000,
        ]);

        $errors = $service->validateCollateral($app);
        $this->assertEmpty($errors);
    }

    public function test_validate_collateral_ignores_rejected_collaterals(): void
    {
        $service = app(CollateralRequirementService::class);
        $plan = $this->makePlan();
        $this->makeRule($plan, ['minimum_assets' => 1]);
        $app = $this->makeApplication($plan);

        LoanApplicationCollateral::factory()->rejected()->create([
            'loan_application_id' => $app->id,
            'estimated_value' => 1000000,
        ]);

        $errors = $service->validateCollateral($app);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Minimum 1 collateral asset(s) required', $errors[0]);
    }

    public function test_validate_collateral_uses_reviewed_value_when_available(): void
    {
        $service = app(CollateralRequirementService::class);
        $plan = $this->makePlan();
        $this->makeRule($plan, [
            'coverage_percentage' => 100,
            'minimum_collateral_value' => 5000000,
            'minimum_assets' => 1,
            'maximum_assets' => 3,
            'allowed_collateral_types' => ['land'],
            'required_document_types' => [],
        ]);
        $app = $this->makeApplication($plan, 1000000);

        LoanApplicationCollateral::factory()->create([
            'loan_application_id' => $app->id,
            'collateral_type' => \App\Enums\CollateralType::Land,
            'estimated_value' => 1000000,
            'reviewed_value' => 6000000,
        ]);

        $errors = $service->validateCollateral($app);
        $this->assertEmpty($errors);
    }

    // ---------------------------------------------------------------
    // Snapshot Tests
    // ---------------------------------------------------------------

    public function test_create_snapshot_stores_rule_data(): void
    {
        $service = app(CollateralRequirementService::class);
        $plan = $this->makePlan();
        $rule = $this->makeRule($plan);
        $app = $this->makeApplication($plan, 2000000);

        $snapshot = $service->createSnapshot($app);

        $this->assertEquals($app->id, $snapshot->loan_application_id);
        $this->assertEquals($plan->id, $snapshot->loan_plan_id);
        $this->assertEquals($rule->id, $snapshot->collateral_rule_id);
        $this->assertTrue($snapshot->collateral_required);
        $this->assertEquals(2000000.0, (float) $snapshot->requested_amount);
        $this->assertGreaterThan(0, (float) $snapshot->minimum_collateral_value);
        $this->assertEquals(['land', 'vehicle', 'building'], $snapshot->allowed_collateral_types);
        $this->assertEquals(['ownership_document'], $snapshot->required_document_types);
        $this->assertNotNull($snapshot->snapshot_created_at);
    }

    public function test_create_snapshot_without_rule(): void
    {
        $service = app(CollateralRequirementService::class);
        $plan = $this->makePlan(['requires_collateral' => false]);
        $app = $this->makeApplication($plan);

        $snapshot = $service->createSnapshot($app);

        $this->assertFalse($snapshot->collateral_required);
        $this->assertNull($snapshot->collateral_rule_id);
        $this->assertEquals(0, (float) $snapshot->minimum_collateral_value);
    }

    // ---------------------------------------------------------------
    // Multiple Rules (Tiered) Tests
    // ---------------------------------------------------------------

    public function test_tiered_rules_resolve_correct_amount_tier(): void
    {
        $service = app(CollateralRequirementService::class);
        $plan = $this->makePlan();

        $this->makeRule($plan, [
            'minimum_amount' => 0, 'maximum_amount' => 500000,
            'collateral_required' => false, 'coverage_percentage' => 0,
            'minimum_collateral_value' => 0,
        ]);
        $this->makeRule($plan, [
            'minimum_amount' => 500001, 'maximum_amount' => 5000000,
            'collateral_required' => true, 'coverage_percentage' => 150,
            'minimum_collateral_value' => 1000000,
        ]);
        $this->makeRule($plan, [
            'minimum_amount' => 5000001, 'maximum_amount' => 50000000,
            'collateral_required' => true, 'coverage_percentage' => 200,
            'minimum_collateral_value' => 10000000,
        ]);

        $this->assertFalse($service->isCollateralRequired($plan, 300000));
        $this->assertTrue($service->isCollateralRequired($plan, 2000000));
        $this->assertEquals(3000000.0, $service->calculateRequiredValue($plan, 2000000));
        $this->assertEquals(7500000.0, $service->calculateRequiredValue($plan, 5000000));
    }

    // ---------------------------------------------------------------
    // Controller / Route Tests
    // ---------------------------------------------------------------

    public function test_admin_can_verify_collateral(): void
    {
        $plan = $this->makePlan();
        $this->makeRule($plan);
        $app = $this->makeApplication($plan);
        $app->update(['status' => \App\Enums\LoanApplicationStatus::UnderReview]);
        $collateral = LoanApplicationCollateral::factory()->create([
            'loan_application_id' => $app->id,
            'estimated_value' => 1000000,
        ]);

        $response = $this->actingAs($this->admin)->post(
            route('loan-applications.collaterals.verify', [$app, $collateral]),
            ['reviewed_value' => 1200000]
        );

        $response->assertRedirect();
        $collateral->refresh();
        $this->assertEquals(LoanCollateralStatus::Verified, $collateral->status);
        $this->assertEquals(1200000.0, (float) $collateral->reviewed_value);
    }

    public function test_admin_can_reject_collateral(): void
    {
        $plan = $this->makePlan();
        $this->makeRule($plan);
        $app = $this->makeApplication($plan);
        $app->update(['status' => \App\Enums\LoanApplicationStatus::UnderReview]);
        $collateral = LoanApplicationCollateral::factory()->create([
            'loan_application_id' => $app->id,
        ]);

        $response = $this->actingAs($this->admin)->post(
            route('loan-applications.collaterals.reject', [$app, $collateral]),
            ['rejection_reason' => 'Insufficient documentation']
        );

        $response->assertRedirect();
        $collateral->refresh();
        $this->assertEquals(LoanCollateralStatus::Rejected, $collateral->status);
        $this->assertEquals('Insufficient documentation', $collateral->review_notes);
    }

    public function test_admin_can_add_collateral(): void
    {
        $plan = $this->makePlan();
        $app = $this->makeApplication($plan);

        $response = $this->actingAs($this->admin)->post(
            route('loan-applications.collaterals.store', $app),
            [
                'collateral_type' => 'land',
                'description' => 'Agricultural land in Arusha',
                'estimated_value' => 5000000,
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('loan_application_collaterals', [
            'loan_application_id' => $app->id,
            'collateral_type' => 'land',
            'status' => 'pending',
        ]);
    }

    public function test_admin_can_remove_collateral(): void
    {
        $plan = $this->makePlan();
        $app = $this->makeApplication($plan);
        $collateral = LoanApplicationCollateral::factory()->create([
            'loan_application_id' => $app->id,
        ]);

        $response = $this->actingAs($this->admin)->delete(
            route('loan-applications.collaterals.destroy', [$app, $collateral])
        );

        $response->assertRedirect();
        $this->assertDatabaseMissing('loan_application_collaterals', ['id' => $collateral->id]);
    }

    public function test_collateral_verify_requires_reviewed_value(): void
    {
        $plan = $this->makePlan();
        $app = $this->makeApplication($plan);
        $collateral = LoanApplicationCollateral::factory()->create([
            'loan_application_id' => $app->id,
        ]);

        $response = $this->actingAs($this->admin)->post(
            route('loan-applications.collaterals.verify', [$app, $collateral]),
            []
        );

        $response->assertSessionHasErrors('reviewed_value');
    }

    public function test_collateral_reject_requires_reason(): void
    {
        $plan = $this->makePlan();
        $app = $this->makeApplication($plan);
        $collateral = LoanApplicationCollateral::factory()->create([
            'loan_application_id' => $app->id,
        ]);

        $response = $this->actingAs($this->admin)->post(
            route('loan-applications.collaterals.reject', [$app, $collateral]),
            []
        );

        $response->assertSessionHasErrors('rejection_reason');
    }

    // ---------------------------------------------------------------
    // Get Missing Documents Tests
    // ---------------------------------------------------------------

    public function test_get_missing_documents_returns_empty_when_no_docs_required(): void
    {
        $service = app(CollateralRequirementService::class);
        $plan = $this->makePlan();
        $rule = $this->makeRule($plan, ['required_document_types' => []]);
        $app = $this->makeApplication($plan);
        $collateral = LoanApplicationCollateral::factory()->create([
            'loan_application_id' => $app->id,
        ]);

        $missing = $service->getMissingDocuments($collateral, $rule);
        $this->assertEmpty($missing);
    }

    public function test_get_missing_documents_returns_all_when_none_uploaded(): void
    {
        $service = app(CollateralRequirementService::class);
        $plan = $this->makePlan();
        $rule = $this->makeRule($plan, ['required_document_types' => ['ownership_document', 'valuation_report']]);
        $app = $this->makeApplication($plan);
        $collateral = LoanApplicationCollateral::factory()->create([
            'loan_application_id' => $app->id,
        ]);

        $missing = $service->getMissingDocuments($collateral, $rule);
        $this->assertCount(2, $missing);
        $this->assertContains('ownership_document', $missing);
        $this->assertContains('valuation_report', $missing);
    }

    // ---------------------------------------------------------------
    // Model Tests — LoanApplicationCollateral Snapshot Relationship
    // ---------------------------------------------------------------

    public function test_application_has_one_collateral_snapshot(): void
    {
        $plan = $this->makePlan();
        $this->makeRule($plan);
        $app = $this->makeApplication($plan);

        $this->assertNull($app->collateralSnapshot);

        app(CollateralRequirementService::class)->createSnapshot($app);

        $app->refresh();
        $this->assertNotNull($app->collateralSnapshot);
        $this->assertTrue($app->collateralSnapshot->collateral_required);
    }

    // ---------------------------------------------------------------
    // CollateralDocument Model Tests
    // ---------------------------------------------------------------

    public function test_collateral_document_belongs_to_collateral(): void
    {
        $plan = $this->makePlan();
        $app = $this->makeApplication($plan);
        $collateral = LoanApplicationCollateral::factory()->create([
            'loan_application_id' => $app->id,
        ]);

        $doc = \App\Models\CollateralDocument::create([
            'loan_application_collateral_id' => $collateral->id,
            'document_type' => 'ownership_document',
            'file_path' => 'test/path.pdf',
            'original_filename' => 'ownership.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
        ]);

        $this->assertEquals($collateral->id, $doc->collateral->id);
    }

    public function test_collateral_has_documents_relationship(): void
    {
        $plan = $this->makePlan();
        $app = $this->makeApplication($plan);
        $collateral = LoanApplicationCollateral::factory()->create([
            'loan_application_id' => $app->id,
        ]);

        $this->assertCount(0, $collateral->documents);

        \App\Models\CollateralDocument::create([
            'loan_application_collateral_id' => $collateral->id,
            'document_type' => 'ownership_document',
            'file_path' => 'test/path.pdf',
            'original_filename' => 'ownership.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
        ]);

        $this->assertCount(1, $collateral->fresh()->documents);
    }

    public function test_collateral_get_effective_value(): void
    {
        $collateral = LoanApplicationCollateral::factory()->create([
            'estimated_value' => 1000000,
            'reviewed_value' => null,
        ]);
        $this->assertEquals(1000000.0, $collateral->getEffectiveValue());

        $collateral->update(['reviewed_value' => 1500000]);
        $this->assertEquals(1500000.0, $collateral->getEffectiveValue());
    }
}
