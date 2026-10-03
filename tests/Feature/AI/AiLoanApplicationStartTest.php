<?php

namespace Tests\Feature\AI;

use App\AI\DTOs\AiContextData;
use App\AI\Services\AiChatOrchestrationService;
use App\AI\Services\AiContextBuilderService;
use App\Enums\LoanPlanStatus;
use App\Enums\LoanStatus;
use App\Enums\MemberStatus;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanPlan;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Acceptance coverage for loan eligibility question handling.
 *
 * Two questions that look alike must not be treated alike:
 *
 *  - "Can I apply for a loan?" is a workflow question. It is answered from the
 *    member's real status and the plans they may choose from.
 *  - "Am I eligible for a <plan> of <amount>?" is a calculation, and is only
 *    ever answered by LoanEligibilityService with values the member supplied.
 *
 * The recurring assertion across this file is that no path invents a loan plan
 * or an amount. Where a plan or amount cannot be resolved unambiguously, the
 * capability must fall back to the workflow answer and let the assistant ask.
 */
class AiLoanApplicationStartTest extends AiTestCase
{
    private function plan(): AiChatOrchestrationService
    {
        return app(AiChatOrchestrationService::class);
    }

    private function contextFor(User $user): AiContextData
    {
        return app(AiContextBuilderService::class)->build($user);
    }

    private function businessPlan(Organization $org, string $name = 'Business Loan'): LoanPlan
    {
        return LoanPlan::factory()->create([
            'organization_id' => $org->id,
            'name' => $name,
            'code' => 'BUS-'.uniqid(),
            'minimum_amount' => 100000,
            'maximum_amount' => 10000000,
            'minimum_term' => 3,
            'maximum_term' => 24,
            'status' => LoanPlanStatus::Active,
        ]);
    }

    private function callTool(User $user, string $capability, array $arguments = [])
    {
        return $this->actingAs($user)->postJson('/ai/tool', [
            'capability' => $capability,
            'arguments' => $arguments,
            'question' => 'Please report.',
        ]);
    }

    public function test_application_start_question_reports_workflow_state_and_no_verdict(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);
        $user = $this->vicobaUser($org, $member);
        $this->businessPlan($org);

        $plan = $this->plan()->plan($this->contextFor($user), 'Can I apply for a loan?');

        $this->assertNotNull($plan);
        $this->assertSame('ai.loan.application.start', $plan->capability);

        $response = $this->callTool($user, $plan->capability, $plan->arguments)->assertOk();
        $result = $response->json('data.result');

        $this->assertTrue($result['can_start']);
        $this->assertSame([], $result['blocking_reasons']);
        $this->assertSame(['loan_plan', 'requested_amount'], $result['requirements_for_eligibility']);

        $this->assertArrayNotHasKey(
            'eligible',
            $result,
            'A workflow question must never receive an eligibility verdict.',
        );
        $this->assertArrayNotHasKey('approved_amount', $result);
        $this->assertArrayNotHasKey('loan_plan_id', $result['available_plans'][0]);
    }

    public function test_application_start_lists_only_active_plans_from_the_members_own_organization(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);
        $user = $this->vicobaUser($org, $member);

        $this->businessPlan($org, 'Business Loan');

        LoanPlan::factory()->create([
            'organization_id' => $org->id,
            'name' => 'Retired Scheme',
            'code' => 'RET-'.uniqid(),
            'status' => LoanPlanStatus::Inactive,
        ]);

        $other = $this->makeOrganization();
        $this->businessPlan($other, 'Other Org Business Loan');

        $result = $this->callTool($user, 'ai.loan.application.start', [])->assertOk()->json('data.result');

        $names = array_column($result['available_plans'], 'name');

        $this->assertSame(['Business Loan'], $names);
    }

    public function test_suspended_member_cannot_start_and_is_told_why(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);
        $user = $this->vicobaUser($org, $member);

        $member->update(['membership_status' => MemberStatus::Suspended]);

        $result = $this->callTool($user, 'ai.loan.application.start', [])->assertOk()->json('data.result');

        $this->assertFalse($result['can_start']);
        $this->assertNotEmpty($result['blocking_reasons']);
        $this->assertStringContainsString('suspended', $result['blocking_reasons'][0]);
        $this->assertSame('suspended', $result['member_status']);
    }

    public function test_in_progress_applications_are_reported_but_never_treated_as_a_block(): void
    {
        $org = $this->makeOrganization();
        $plan = $this->businessPlan($org);
        $member = $this->member($org);
        $user = $this->vicobaUser($org, $member);

        LoanApplication::factory()->create([
            'organization_id' => $org->id,
            'member_id' => $member->id,
            'loan_plan_id' => $plan->id,
            'status' => 'submitted',
        ]);

        $result = $this->callTool($user, 'ai.loan.application.start', [])->assertOk()->json('data.result');

        $this->assertTrue($result['can_start'], 'An in-progress application is not an eligibility block in FinancePro.');
        $this->assertCount(1, $result['in_progress_applications']);
        $this->assertSame('Business Loan', $result['in_progress_applications'][0]['plan_name']);
        $this->assertFalse($result['in_progress_applications_block_start']);
    }

    public function test_specific_plan_and_amount_invokes_the_authoritative_service(): void
    {
        $org = $this->makeOrganization();
        $plan = $this->businessPlan($org);
        $member = $this->member($org);
        $user = $this->vicobaUser($org, $member);

        $routed = $this->plan()->plan(
            $this->contextFor($user),
            'Am I eligible for a Business Loan of 500,000?',
        );

        $this->assertNotNull($routed);
        $this->assertSame('ai.loan.eligibility.check', $routed->capability);
        $this->assertSame((int) $plan->id, (int) $routed->arguments['loan_plan_id']);
        $this->assertSame(500000.0, (float) $routed->arguments['requested_amount']);

        $result = $this->callTool($user, $routed->capability, $routed->arguments)
            ->assertOk()
            ->json('data.result');

        $this->assertTrue($result['eligible']);
        $this->assertSame('LoanEligibilityService', $result['source']);
        $this->assertSame(500000.0, (float) $result['approved_amount']);
    }

    public function test_specific_eligibility_reports_the_real_failure_reason_without_guessing(): void
    {
        $org = $this->makeOrganization();
        $plan = $this->businessPlan($org);
        $member = $this->member($org);
        $user = $this->vicobaUser($org, $member);

        // Below the plan minimum: the real service decides, not the assistant.
        $routed = $this->plan()->plan(
            $this->contextFor($user),
            'Am I eligible for a Business Loan of 5000?',
        );

        $result = $this->callTool($user, $routed->capability, $routed->arguments)
            ->assertOk()
            ->json('data.result');

        $this->assertFalse($result['eligible']);
        $this->assertSame('fail', $result['checks']['amount_in_range']);
        $this->assertSame(0.0, (float) $result['approved_amount']);
        $this->assertNotEmpty($result['failure_reasons']);
    }

    public function test_existing_unpaid_loan_restriction_comes_from_the_real_service(): void
    {
        $org = $this->makeOrganization();
        $plan = $this->businessPlan($org);
        $member = $this->member($org);
        $user = $this->vicobaUser($org, $member);

        Loan::factory()->create([
            'organization_id' => $org->id,
            'member_id' => $member->id,
            'loan_plan_id' => $plan->id,
            'status' => LoanStatus::Active,
            'total_amount' => 1000000,
            'amount_paid' => 100000,
        ]);

        $routed = $this->plan()->plan(
            $this->contextFor($user),
            'Am I eligible for a Business Loan of 500,000?',
        );

        $result = $this->callTool($user, $routed->capability, $routed->arguments)
            ->assertOk()
            ->json('data.result');

        $this->assertFalse($result['eligible']);
        $this->assertSame('fail', $result['checks']['active_loans_limit']);
        $this->assertStringContainsString('85%', implode(' ', $result['failure_reasons']));
    }

    public function test_missing_plan_falls_back_to_the_workflow_answer(): void
    {
        $org = $this->makeOrganization();
        $this->businessPlan($org);
        $member = $this->member($org);
        $user = $this->vicobaUser($org, $member);

        $plan = $this->plan()->plan($this->contextFor($user), 'Am I eligible for a loan of 500,000?');

        $this->assertNotNull($plan);
        $this->assertSame('ai.loan.application.start', $plan->capability);
        $this->assertArrayNotHasKey('requested_amount', $plan->arguments);
    }

    public function test_missing_amount_falls_back_to_the_workflow_answer(): void
    {
        $org = $this->makeOrganization();
        $this->businessPlan($org);
        $member = $this->member($org);
        $user = $this->vicobaUser($org, $member);

        $plan = $this->plan()->plan($this->contextFor($user), 'Am I eligible for a Business Loan?');

        $this->assertNotNull($plan);
        $this->assertSame('ai.loan.application.start', $plan->capability);
        $this->assertArrayNotHasKey('requested_amount', $plan->arguments);
    }

    public function test_unknown_plan_name_is_never_resolved_to_an_arbitrary_plan(): void
    {
        $org = $this->makeOrganization();
        $this->businessPlan($org);
        $this->businessPlan($org, 'Agricultural Loan');
        $member = $this->member($org);
        $user = $this->vicobaUser($org, $member);

        $plan = $this->plan()->plan(
            $this->contextFor($user),
            'Am I eligible for a Spacecraft Loan of 500,000?',
        );

        $this->assertNotNull($plan);
        $this->assertSame('ai.loan.application.start', $plan->capability);
    }

    public function test_ambiguous_plan_names_are_never_resolved_automatically(): void
    {
        $org = $this->makeOrganization();
        $this->businessPlan($org, 'Business');
        $this->businessPlan($org, 'Business Loan');
        $member = $this->member($org);
        $user = $this->vicobaUser($org, $member);

        $plan = $this->plan()->plan(
            $this->contextFor($user),
            'Am I eligible for a business loan of 500,000?',
        );

        $this->assertNotNull($plan);
        $this->assertSame(
            'ai.loan.application.start',
            $plan->capability,
            'Two plans match the question, so the member must choose rather than the assistant.',
        );
    }

    public function test_two_different_amounts_are_treated_as_ambiguous(): void
    {
        $org = $this->makeOrganization();
        $this->businessPlan($org);
        $member = $this->member($org);
        $user = $this->vicobaUser($org, $member);

        $plan = $this->plan()->plan(
            $this->contextFor($user),
            'Am I eligible for a Business Loan of 500,000 or 900,000?',
        );

        $this->assertNotNull($plan);
        $this->assertSame('ai.loan.application.start', $plan->capability);
    }

    public function test_swahili_workflow_question_answers_in_swahili_without_a_verdict(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);
        $user = $this->vicobaUser($org, $member);
        $this->businessPlan($org);

        $plan = $this->plan()->plan($this->contextFor($user), 'Je, ninaweza kuomba mkopo?');

        $this->assertNotNull($plan);
        $this->assertSame('ai.loan.application.start', $plan->capability);

        $result = $this->callTool($user, $plan->capability, $plan->arguments)->assertOk()->json('data.result');

        $this->assertTrue($result['can_start']);
        $this->assertArrayNotHasKey('eligible', $result);
    }

    public function test_swahili_specific_eligibility_resolves_the_plan_through_terminology(): void
    {
        $org = $this->makeOrganization();
        $plan = $this->businessPlan($org);
        $member = $this->member($org);
        $user = $this->vicobaUser($org, $member);

        $routed = $this->plan()->plan(
            $this->contextFor($user),
            'Je, ninastahili mkopo wa 500,000 wa biashara?',
        );

        $this->assertNotNull($routed);
        $this->assertSame('ai.loan.eligibility.check', $routed->capability);
        $this->assertSame((int) $plan->id, (int) $routed->arguments['loan_plan_id']);
        $this->assertSame(500000.0, (float) $routed->arguments['requested_amount']);

        $result = $this->callTool($user, $routed->capability, $routed->arguments)
            ->assertOk()
            ->json('data.result');

        $this->assertSame('Business Loan', $result['plan_name']);
        $this->assertSame(500000.0, (float) $result['requested_amount']);
    }

    public function test_staff_cannot_resolve_a_member_from_another_organization(): void
    {
        $mine = $this->makeOrganization();
        $theirs = $this->makeOrganization();

        $foreignMember = Member::factory()->create([
            'organization_id' => $theirs->id,
            'membership_status' => MemberStatus::Active,
        ]);

        $staff = $this->staff($mine);

        $this->callTool($staff, 'ai.loan.application.start', [
            'member_number' => $foreignMember->member_number,
        ])->assertForbidden();
    }

    public function test_member_without_the_application_permission_cannot_start_one(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);

        // A role that can chat but was never granted the loan-application
        // capability, so the permission gate itself is what is under test.
        $chatOnly = Role::firstOrCreate(['name' => 'Chat Only', 'guard_name' => 'web']);
        $chatOnly->syncPermissions(['ai.view', 'ai.use']);

        $user = $this->user('Chat Only');
        $user->organizations()->attach($org->id);
        $member->update(['user_id' => $user->id]);

        $this->callTool($user, 'ai.loan.application.start', [])->assertForbidden();
    }

    public function test_specific_eligibility_is_not_available_to_chat_only_role(): void
    {
        $org = $this->makeOrganization();
        $plan = $this->businessPlan($org);
        $member = $this->member($org);

        $chatOnly = Role::firstOrCreate(['name' => 'Chat Only', 'guard_name' => 'web']);
        $chatOnly->syncPermissions(['ai.view', 'ai.use']);

        $user = $this->user('Chat Only');
        $user->organizations()->attach($org->id);
        $member->update(['user_id' => $user->id]);

        $this->callTool($user, 'ai.loan.eligibility.check', [
            'loan_plan_id' => $plan->id,
            'requested_amount' => 500000,
        ])->assertForbidden();
    }
}
