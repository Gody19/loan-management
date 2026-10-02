<?php

namespace Tests\Feature\AI;

use App\AI\ManagementActions\Services\ManagementActionQueryService;
use App\AI\ManagementCenter\Services\ManagementIntelligenceCenterService;
use App\AI\Services\AiContextBuilderService;
use App\Enums\ManagementActionPriority;
use App\Enums\ManagementActionStatus;
use App\Enums\ProactiveInsightSeverity;
use App\Enums\ProactiveInsightSource;
use App\Enums\ProactiveInsightStatus;
use App\Enums\ProactiveInsightType;
use App\Models\AiInsight;
use App\Models\Branch;
use App\Models\ManagementAction;
use App\Models\ManagementActionEvent;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ManagementActionAssignedNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

/**
 * Executive Action & Review Management (Phase 12.3).
 *
 * A management action is a human workflow record raised around advisory
 * intelligence. These tests verify its load-bearing guarantees: capability-
 * gated access (view / manage / assign), trusted tenant and branch scope that a
 * request can never widen, an immutable append-only timeline, terminal states
 * that can never be reopened, human-only assignment (never an AI output) and a
 * read-only Intelligence Center panel that never writes an action.
 */
class ManagementActionTest extends AiTestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function actionUser(Organization $org, ?Branch $branch = null, string $role = 'Organization Administrator'): User
    {
        $user = $this->staff($org, $role);

        if ($branch !== null) {
            $user->branches()->attach($branch->id);
        }

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeAction(Organization $org, ?Branch $branch, User $creator, array $overrides = []): ManagementAction
    {
        return ManagementAction::create(array_merge([
            'organization_id' => $org->id,
            'branch_id' => $branch?->id,
            'created_by' => $creator->id,
            'assigned_to' => null,
            'title' => 'Action '.uniqid(),
            'description' => null,
            'priority' => ManagementActionPriority::Medium->value,
            'status' => ManagementActionStatus::Open->value,
            'due_date' => null,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeInsight(Organization $org, ?Branch $branch = null, array $overrides = []): AiInsight
    {
        return AiInsight::create(array_merge([
            'organization_id' => $org->id,
            'branch_id' => $branch?->id,
            'type' => ProactiveInsightType::OverdueLoan->value,
            'severity' => ProactiveInsightSeverity::Warning->value,
            'title' => 'Insight '.uniqid(),
            'summary' => 'A deterministic advisory.',
            'recommendation' => 'Review the underlying record.',
            'source_type' => ProactiveInsightSource::Loan->value,
            'dedup_key' => 'insight-'.uniqid(),
            'status' => ProactiveInsightStatus::New->value,
            'generated_at' => now(),
        ], $overrides));
    }

    // ------------------------------------------------------------------
    // Access control
    // ------------------------------------------------------------------

    public function test_a_viewer_can_open_the_action_center(): void
    {
        $org = $this->makeOrganization();
        $user = $this->actionUser($org, $this->branch($org), 'Organization Administrator');

        $this->actingAs($user)->get(route('ai.actions.index'))->assertOk()->assertSee('Management Actions');
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('ai.actions.index'))->assertRedirect(route('login'));
    }

    public function test_roles_without_action_capability_are_forbidden(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);

        foreach (['Secretary'] as $role) {
            $this->actingAs($this->actionUser($org, $branch, $role))
                ->get(route('ai.actions.index'))
                ->assertForbidden();
        }

        $this->actingAs($this->vicobaUser($org, $this->member($org)))
            ->get(route('ai.actions.index'))
            ->assertForbidden();
    }

    public function test_an_auditor_may_view_but_may_not_manage_or_assign(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $auditor = $this->actionUser($org, $branch, 'Auditor');
        $action = $this->makeAction($org, $branch, $auditor);

        $this->actingAs($auditor)->get(route('ai.actions.index'))->assertOk();
        $this->actingAs($auditor)->get(route('ai.actions.show', $action))->assertOk();
        $this->actingAs($auditor)->get(route('ai.actions.create'))->assertForbidden();
        $this->actingAs($auditor)->post(route('ai.actions.assign', $action), ['assigned_to' => $auditor->id])->assertForbidden();
    }

    public function test_a_manage_only_officer_cannot_assign_to_another_user(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $officer = $this->actionUser($org, $branch, 'Loan Officer');
        $action = $this->makeAction($org, $branch, $officer);

        $this->actingAs($officer)
            ->post(route('ai.actions.assign', $action), ['assigned_to' => $officer->id])
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Creation
    // ------------------------------------------------------------------

    public function test_a_manager_can_create_an_action(): void
    {
        $org = $this->makeOrganization();
        $manager = $this->actionUser($org);

        $response = $this->actingAs($manager)->post(route('ai.actions.store'), [
            'title' => 'Follow up the collection shortfall',
            'description' => 'Contact the borrower and record the outcome.',
            'priority' => ManagementActionPriority::High->value,
            'due_date' => now()->addWeek()->toDateString(),
        ]);

        $action = ManagementAction::firstOrFail();

        $response->assertRedirect(route('ai.actions.show', $action));
        $this->assertSame(ManagementActionStatus::Open, $action->status);
        $this->assertSame(ManagementActionPriority::High, $action->priority);
        $this->assertSame($org->id, $action->organization_id);
        $this->assertNull($action->assigned_to);
    }

    public function test_a_created_action_records_the_created_event_and_audit_trail(): void
    {
        $org = $this->makeOrganization();
        $manager = $this->actionUser($org);

        $this->actingAs($manager)->post(route('ai.actions.store'), ['title' => 'A tracked follow-up'])->assertRedirect();

        $action = ManagementAction::firstOrFail();

        $this->assertDatabaseHas('management_action_events', [
            'management_action_id' => $action->id,
            'event' => 'created',
            'new_status' => ManagementActionStatus::Open->value,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'ai.management_action.created',
            'auditable_id' => $action->id,
        ]);
    }

    // ------------------------------------------------------------------
    // Branch scope
    // ------------------------------------------------------------------

    public function test_a_branch_limited_user_must_select_a_branch(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $manager = $this->actionUser($org, $branch, 'Branch Manager');

        $this->actingAs($manager)
            ->post(route('ai.actions.store'), ['title' => 'An organization-wide attempt'])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('management_actions', 0);
    }

    public function test_a_branch_limited_user_cannot_create_in_an_unassigned_branch(): void
    {
        $org = $this->makeOrganization();
        $mine = $this->branch($org);
        $other = $this->branch($org);
        $manager = $this->actionUser($org, $mine, 'Branch Manager');

        $this->actingAs($manager)
            ->post(route('ai.actions.store'), ['title' => 'Sneaky', 'branch_id' => $other->id])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('management_actions', 0);
    }

    public function test_a_branch_limited_user_can_create_in_their_own_branch(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $manager = $this->actionUser($org, $branch, 'Branch Manager');

        $this->actingAs($manager)
            ->post(route('ai.actions.store'), ['title' => 'In my branch', 'branch_id' => $branch->id])
            ->assertRedirect();

        $this->assertSame($branch->id, ManagementAction::firstOrFail()->branch_id);
    }

    // ------------------------------------------------------------------
    // Tenant isolation
    // ------------------------------------------------------------------

    public function test_a_foreign_action_is_not_viewable(): void
    {
        $mine = $this->makeOrganization();
        $user = $this->actionUser($mine);

        $theirs = $this->makeOrganization();
        $foreign = $this->makeAction($theirs, null, $this->actionUser($theirs));

        $this->actingAs($user)->get(route('ai.actions.show', $foreign))->assertNotFound();
    }

    public function test_branch_scope_limits_the_action_center(): void
    {
        $org = $this->makeOrganization();
        $mine = $this->branch($org);
        $other = $this->branch($org);
        $manager = $this->actionUser($org, $mine, 'Branch Manager');

        $inScope = $this->makeAction($org, $mine, $manager, ['title' => 'VISIBLE-IN-BRANCH']);
        $outOfScope = $this->makeAction($org, $other, $manager, ['title' => 'HIDDEN-OTHER-BRANCH']);

        $response = $this->actingAs($manager)->get(route('ai.actions.index'));

        $response->assertOk();
        $response->assertSee($inScope->title);
        $response->assertDontSee($outOfScope->title);
    }

    public function test_an_organization_wide_action_is_invisible_to_a_branch_limited_user(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $manager = $this->actionUser($org, $branch, 'Branch Manager');
        $orgWide = $this->makeAction($org, null, $manager, ['title' => 'ORG-WIDE-HIDDEN']);

        $this->actingAs($manager)->get(route('ai.actions.show', $orgWide))->assertNotFound();
        $this->actingAs($manager)->get(route('ai.actions.index'))->assertOk()->assertDontSee($orgWide->title);

        $orgWideViewer = $this->actionUser($org);
        $this->actingAs($orgWideViewer)->get(route('ai.actions.show', $orgWide))->assertOk();
    }

    // ------------------------------------------------------------------
    // Lifecycle
    // ------------------------------------------------------------------

    public function test_the_lifecycle_records_an_ordered_append_only_timeline(): void
    {
        $org = $this->makeOrganization();
        $manager = $this->actionUser($org);

        $this->actingAs($manager)->post(route('ai.actions.store'), ['title' => 'Lifecycle'])->assertRedirect();
        $action = ManagementAction::firstOrFail();

        $this->actingAs($manager)->post(route('ai.actions.start', $action))->assertSessionHas('success');
        $this->assertSame(ManagementActionStatus::InProgress, $action->fresh()->status);

        $this->actingAs($manager)
            ->post(route('ai.actions.complete', $action), ['completion_notes' => 'Done and verified.'])
            ->assertSessionHas('success');

        $action->refresh();
        $this->assertSame(ManagementActionStatus::Completed, $action->status);
        $this->assertNotNull($action->completed_at);
        $this->assertSame('Done and verified.', $action->completion_notes);

        $events = ManagementActionEvent::where('management_action_id', $action->id)->orderBy('id')->pluck('event')->all();
        $this->assertSame(['created', 'started', 'completed'], $events);
        $this->assertDatabaseHas('audit_logs', ['event' => 'ai.management_action.completed', 'auditable_id' => $action->id]);
    }

    public function test_a_completed_action_is_immutable(): void
    {
        $org = $this->makeOrganization();
        $manager = $this->actionUser($org);
        $action = $this->makeAction($org, null, $manager, ['title' => 'Original title']);

        $this->actingAs($manager)->post(route('ai.actions.complete', $action))->assertSessionHas('success');
        $this->actingAs($manager)->put(route('ai.actions.update', $action), ['title' => 'Changed'])->assertSessionHas('error');

        $this->assertSame('Original title', $action->fresh()->title);
    }

    public function test_an_invalid_transition_is_rejected(): void
    {
        $org = $this->makeOrganization();
        $manager = $this->actionUser($org);
        $action = $this->makeAction($org, null, $manager);

        $this->actingAs($manager)->post(route('ai.actions.complete', $action))->assertSessionHas('success');
        $this->actingAs($manager)->post(route('ai.actions.start', $action))->assertSessionHas('error');

        $this->assertSame(ManagementActionStatus::Completed, $action->fresh()->status);
    }

    public function test_cancelling_an_action_is_terminal(): void
    {
        $org = $this->makeOrganization();
        $manager = $this->actionUser($org);
        $action = $this->makeAction($org, null, $manager);

        $this->actingAs($manager)
            ->post(route('ai.actions.cancel', $action), ['cancellation_reason' => 'No longer relevant.'])
            ->assertSessionHas('success');

        $action->refresh();
        $this->assertSame(ManagementActionStatus::Cancelled, $action->status);
        $this->assertSame('No longer relevant.', $action->cancellation_reason);
        $this->assertTrue($action->isTerminal());

        $this->actingAs($manager)->post(route('ai.actions.cancel', $action))->assertSessionHas('error');
        $this->assertSame(ManagementActionStatus::Cancelled, $action->fresh()->status);
    }

    // ------------------------------------------------------------------
    // Assignment
    // ------------------------------------------------------------------

    public function test_assignment_notifies_the_assignee(): void
    {
        Notification::fake();

        $org = $this->makeOrganization();
        $manager = $this->actionUser($org);
        $assignee = $this->actionUser($org, null, 'Loan Officer');
        $action = $this->makeAction($org, null, $manager);

        $this->actingAs($manager)
            ->post(route('ai.actions.assign', $action), ['assigned_to' => $assignee->id])
            ->assertSessionHas('success');

        $this->assertSame($assignee->id, $action->fresh()->assigned_to);
        Notification::assertSentTo($assignee, ManagementActionAssignedNotification::class);
        $this->assertDatabaseHas('management_action_events', [
            'management_action_id' => $action->id,
            'event' => 'assigned',
            'new_assignee_id' => $assignee->id,
        ]);
    }

    public function test_an_action_cannot_be_assigned_to_a_user_without_read_access(): void
    {
        $org = $this->makeOrganization();
        $manager = $this->actionUser($org);
        $blind = $this->actionUser($org, null, 'Secretary');
        $action = $this->makeAction($org, null, $manager);

        $this->actingAs($manager)
            ->post(route('ai.actions.assign', $action), ['assigned_to' => $blind->id])
            ->assertSessionHas('error');

        $this->assertNull($action->fresh()->assigned_to);
    }

    // ------------------------------------------------------------------
    // Source provenance
    // ------------------------------------------------------------------

    public function test_an_action_may_be_raised_from_an_authorized_source(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $manager = $this->actionUser($org);
        $insight = $this->makeInsight($org, $branch, ['title' => 'Source insight']);

        $this->actingAs($manager)->post(route('ai.actions.store'), [
            'title' => 'From an insight',
            'source_type' => 'insight',
            'source_id' => $insight->id,
        ])->assertRedirect();

        $action = ManagementAction::firstOrFail();
        $this->assertSame(AiInsight::class, $action->source_type);
        $this->assertSame($insight->id, $action->source_id);
        $this->assertSame('Source insight', $action->source_label);
    }

    public function test_an_action_cannot_be_raised_from_a_foreign_source(): void
    {
        $org = $this->makeOrganization();
        $manager = $this->actionUser($org);

        $theirs = $this->makeOrganization();
        $foreign = $this->makeInsight($theirs, null, ['title' => 'Foreign insight']);

        $this->actingAs($manager)->post(route('ai.actions.store'), [
            'title' => 'Should not attach',
            'source_type' => 'insight',
            'source_id' => $foreign->id,
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('management_actions', 0);
    }

    // ------------------------------------------------------------------
    // Overdue & dashboard
    // ------------------------------------------------------------------

    public function test_overdue_actions_are_counted(): void
    {
        $org = $this->makeOrganization();
        $manager = $this->actionUser($org);
        $context = app(AiContextBuilderService::class)->build($manager);

        $this->makeAction($org, null, $manager, ['due_date' => CarbonImmutable::today()->subDay()->toDateString()]);
        $this->makeAction($org, null, $manager, ['due_date' => CarbonImmutable::today()->addWeek()->toDateString()]);

        $dashboard = app(ManagementActionQueryService::class)->dashboard($context);

        $this->assertSame(1, $dashboard['overdue']);
        $this->assertSame(2, $dashboard['open']);
        $this->assertSame(2, $dashboard['unassigned']);
    }

    // ------------------------------------------------------------------
    // Intelligence Center integration
    // ------------------------------------------------------------------

    public function test_the_center_shows_the_management_follow_up_panel_and_never_writes(): void
    {
        $org = $this->makeOrganization();
        $manager = $this->actionUser($org);
        $action = $this->makeAction($org, null, $manager, ['title' => 'RECENTLY-COMPLETED-PANEL-TITLE']);

        $this->actingAs($manager)->post(route('ai.actions.complete', $action))->assertSessionHas('success');

        $before = ManagementAction::count();
        $eventsBefore = ManagementActionEvent::count();

        $response = $this->actingAs($manager)->get(route('ai.intelligence-center.index'));

        $response->assertOk();
        $response->assertSee('Management follow-up');
        $response->assertSee($action->title);
        $this->assertSame($before, ManagementAction::count(), 'Viewing the center must not create a management action.');
        $this->assertSame($eventsBefore, ManagementActionEvent::count(), 'Viewing the center must not append a timeline event.');
    }

    public function test_the_center_hides_the_follow_up_panel_without_the_view_capability(): void
    {
        $org = $this->makeOrganization();

        $reader = $this->user();
        $reader->organizations()->attach($org->id);
        $reader->givePermissionTo('ai.portfolio.view');

        $context = app(AiContextBuilderService::class)->build($reader);
        $overview = app(ManagementIntelligenceCenterService::class)->overview($context, ['period' => 'this_month']);

        $this->assertFalse($overview['capabilities']['actions']);
        $this->assertFalse($overview['management_follow_up']['available']);

        $this->actingAs($reader)
            ->get(route('ai.intelligence-center.index'))
            ->assertOk()
            ->assertDontSee('Management follow-up');
    }
}
