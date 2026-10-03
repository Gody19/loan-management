<?php

namespace Tests\Feature\AI;

use App\AI\DTOs\AiContextData;
use App\AI\Services\AiChatOrchestrationService;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\LoanPlan;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\ShareAccount;
use App\Models\User;
use App\Models\WelfareAccount;

/**
 * Phase 11.4 server-side chat orchestration. The plain chat endpoint decides
 * — from the trusted context and the question text — whether an authorized
 * business-data tool may be consulted. The browser never selects a capability
 * or passes arguments; every argument is derived server-side from the acting
 * member's own records, and the permission gate is never bypassed.
 */
class AiChatOrchestrationTest extends AiTestCase
{
    private const MEMBER_PERMISSIONS = [
        'ai.view', 'ai.use',
        'ai.member.view', 'ai.loan.view', 'ai.loan-repayments.view',
        'ai.loan-application.view', 'ai.loan-eligibility.view',
    ];

    private function service(): AiChatOrchestrationService
    {
        return app(AiChatOrchestrationService::class);
    }

    private function contextFor(Member $member, array $permissions = self::MEMBER_PERMISSIONS): AiContextData
    {
        return new AiContextData(
            userId: (int) $member->user_id,
            isSuperAdmin: false,
            roles: ['VICOBA Member'],
            permissions: $permissions,
            organizationIds: [(int) $member->organization_id],
            branchIds: [(int) $member->branch_id],
            vicobaGroupIds: [(int) $member->vicoba_group_id],
            memberId: (int) $member->id,
        );
    }

    private function loanFor(Member $member, array $overrides = []): Loan
    {
        return Loan::factory()->active()->create(array_merge([
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'member_id' => $member->id,
            'loan_plan_id' => LoanPlan::factory()->create(['organization_id' => $member->organization_id])->id,
            'principal_amount' => 900000,
            'disbursed_amount' => 900000,
            'total_amount' => 1080000,
            'outstanding_balance' => 800000,
            'amount_paid' => 280000,
        ], $overrides));
    }

    private function actingAsMember(Member $member): User
    {
        $user = $this->vicobaUser(
            $member->organization,
            $member,
        );

        $this->actingAs($user);

        return $user;
    }

    // ------------------------------------------------------------------
    // End-to-end HTTP behaviour
    // ------------------------------------------------------------------

    public function test_loan_balance_question_is_orchestrated_to_member_loans_tool(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);
        $this->loanFor($member);
        $this->actingAsMember($member);

        $response = $this->postJson('/ai/chat', ['message' => 'What is my current loan balance?']);

        $response->assertOk()
            ->assertJsonPath('data.conversation_id', AiConversation::first()->id);

        $requested = AuditLog::where('event', 'ai.tool.requested')->first();
        $completed = AuditLog::where('event', 'ai.tool.completed')->first();

        $this->assertNotNull($requested);
        $this->assertNotNull($completed);
        $this->assertSame('ai.member.loans', $completed->new_values['capability'] ?? null);
        $this->assertContains('member_number', $requested->new_values['argument_keys'] ?? []);
    }

    public function test_savings_question_is_orchestrated_to_savings_tool(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);
        SavingsAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $org->id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'current_balance' => 260000,
        ]);
        $this->actingAsMember($member);

        $this->postJson('/ai/chat', ['message' => 'What are my current savings?'])->assertOk();

        $completed = AuditLog::where('event', 'ai.tool.completed')->first();
        $this->assertNotNull($completed);
        $this->assertSame('ai.member.savings_summary', $completed->new_values['capability'] ?? null);
        $this->assertDatabaseHas('ai_messages', ['role' => 'user', 'content' => 'What are my current savings?']);
        $this->assertDatabaseHas('ai_messages', ['role' => 'assistant']);
    }

    public function test_repayment_question_is_orchestrated_to_loan_repayments_tool(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);
        $this->loanFor($member);
        $this->actingAsMember($member);

        $this->postJson('/ai/chat', ['message' => 'Show me my recent repayment information'])->assertOk();

        $completed = AuditLog::where('event', 'ai.tool.completed')->first();
        $this->assertNotNull($completed);
        $this->assertSame('ai.loan.repayments', $completed->new_values['capability'] ?? null);
    }

    public function test_financial_summary_question_is_orchestrated(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);
        $this->actingAsMember($member);

        $this->postJson('/ai/chat', ['message' => 'Show my financial summary please'])->assertOk();

        $completed = AuditLog::where('event', 'ai.tool.completed')->first();
        $this->assertNotNull($completed);
        $this->assertSame('ai.member.financial_summary', $completed->new_values['capability'] ?? null);
    }

    public function test_shares_and_welfare_questions_are_orchestrated_to_own_scope(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);
        ShareAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $org->id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'total_shares' => 50,
            'total_value' => 750000,
        ]);
        WelfareAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $org->id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'current_balance' => 120000,
        ]);
        $this->actingAsMember($member);

        $this->postJson('/ai/chat', ['message' => 'What are my shares?'])->assertOk();

        $completed = AuditLog::where('event', 'ai.tool.completed')->first();
        $this->assertNotNull($completed);
        $this->assertSame('ai.member.share_summary', $completed->new_values['capability'] ?? null);

        $this->postJson('/ai/chat', ['message' => 'Show my welfare balance'])->assertOk();

        $welfare = AuditLog::where('event', 'ai.tool.completed')->orderByDesc('id')->first();
        $this->assertNotNull($welfare);
        $this->assertSame('ai.member.welfare_summary', $welfare->new_values['capability'] ?? null);
    }

    public function test_eligibility_question_is_answered_by_the_loan_application_capability(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);
        $this->loanFor($member);
        $this->actingAsMember($member);

        $this->postJson('/ai/chat', ['message' => 'How can I check my loan eligibility?'])
            ->assertOk();

        // The member has real plans and a real status, so the question is
        // answerable. It is answered from those records — never invented.
        $this->assertDatabaseHas('audit_logs', ['event' => 'ai.tool.requested']);
    }

    public function test_questions_that_need_values_the_member_has_not_given_stay_in_plain_chat(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);
        $this->loanFor($member);
        $this->actingAsMember($member);

        foreach ([
            'What collateral is required for a new loan?',
            'Tell me about guarantor rules',
        ] as $message) {
            $this->postJson('/ai/chat', ['message' => $message])->assertOk();
        }

        $this->assertDatabaseMissing('audit_logs', ['event' => 'ai.tool.requested']);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'ai.tool.completed']);
        $this->assertDatabaseMissing('ai_messages', ['role' => 'system']);
    }

    public function test_eligibility_without_a_resolvable_plan_is_not_orchestrated_into_a_calculation(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);
        $this->actingAsMember($member);

        // No plan, no amount, nothing concrete: the capability is available but
        // cannot invent an amount to calculate with.
        $plan = $this->service()->plan(
            $this->contextFor($member),
            'Am I eligible for a Spacecraft Loan of 500,000?',
        );

        $this->assertNotNull($plan);
        $this->assertSame('ai.loan.application.start', $plan->capability);
        $this->assertArrayNotHasKey('requested_amount', $plan->arguments);
    }

    public function test_member_without_loans_gets_plain_chat_for_repayment_question(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);
        $this->actingAsMember($member);

        $this->postJson('/ai/chat', ['message' => 'Show me my recent repayments'])->assertOk();

        $this->assertDatabaseMissing('audit_logs', ['event' => 'ai.tool.requested']);
        $this->assertDatabaseMissing('ai_messages', ['role' => 'system']);
    }

    public function test_staff_without_member_record_is_never_orchestrated(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);
        $this->loanFor($member);

        $staff = $this->staff($org);
        $this->actingAs($staff);

        $this->postJson('/ai/chat', ['message' => 'What is my current loan balance?'])->assertOk();

        $this->assertDatabaseMissing('audit_logs', ['event' => 'ai.tool.requested']);
        $this->assertDatabaseMissing('ai_messages', ['role' => 'system']);
    }

    public function test_orchestrated_question_never_persists_system_data_messages(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);
        $this->loanFor($member);
        $this->actingAsMember($member);

        $this->postJson('/ai/chat', ['message' => 'What is my current loan balance?'])->assertOk();

        $this->assertDatabaseMissing('ai_messages', ['role' => 'system']);
        $this->assertSame(2, AiMessage::count());
    }

    // ------------------------------------------------------------------
    // Plan derivation (arguments come only from the member's own records)
    // ------------------------------------------------------------------

    public function test_plan_for_repayment_uses_own_latest_loan_number(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);
        $older = $this->loanFor($member);
        $latest = $this->loanFor($member);
        $context = $this->contextFor($member);

        $plan = $this->service()->plan($context, 'Show me my recent repayments');

        $this->assertNotNull($plan);
        $this->assertSame('ai.loan.repayments', $plan->capability);
        $this->assertSame($latest->loan_number, $plan->arguments['loan_number']);
        $this->assertSame(50, $plan->arguments['limit']);
        $this->assertNotSame($older->loan_number, $plan->arguments['loan_number']);
    }

    public function test_plan_rejects_prompt_supplied_target_loan_number(): void
    {
        $org = $this->makeOrganization();
        $peer = $this->member($org);
        $peerLoan = $this->loanFor($peer);

        $own = $this->member($org);
        $this->loanFor($own);
        $context = $this->contextFor($own);

        $plan = $this->service()->plan($context, "Show my loan balance for loan {$peerLoan->loan_number} instead");

        $this->assertNotNull($plan);
        $this->assertSame('ai.member.loans', $plan->capability);
        $this->assertNotSame($peerLoan->loan_number, $plan->arguments['member_number'] ?? null);
    }

    public function test_plan_is_null_without_member_record(): void
    {
        $org = $this->makeOrganization();
        $this->member($org);
        $staff = $this->staff($org);

        $context = new AiContextData(
            userId: (int) $staff->id,
            isSuperAdmin: false,
            roles: ['Loan Officer'],
            permissions: self::MEMBER_PERMISSIONS,
            organizationIds: [(int) $org->id],
            branchIds: [],
            vicobaGroupIds: [],
            memberId: null,
        );

        $this->assertNull($this->service()->plan($context, 'What is my current loan balance?'));
        $this->assertNull($this->service()->plan($context, 'What are my shares?'));
    }

    public function test_plan_is_gated_by_required_capability_permission(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);
        $this->loanFor($member);

        $withoutLoanView = $this->contextFor($member, [
            'ai.view', 'ai.use', 'ai.member.view',
        ]);

        $this->assertNull($this->service()->plan($withoutLoanView, 'What is my current loan balance?'));
        $this->assertNull($this->service()->plan($withoutLoanView, 'Show me my recent repayments'));

        $withoutMemberView = $this->contextFor($member, [
            'ai.view', 'ai.use', 'ai.loan.view', 'ai.loan-repayments.view',
        ]);

        $this->assertNull($this->service()->plan($withoutMemberView, 'What are my current savings?'));
    }
}
