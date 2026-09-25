<?php

namespace Tests\Feature\AI;

use App\AI\Services\AiConversationService;
use App\Enums\AiFeedbackStatus;
use App\Enums\AiFeedbackType;
use App\Enums\AiMessageRole;
use App\Models\AiConversation;
use App\Models\AiFeedback;
use App\Models\AiMessage;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * Feedback submission: ownership, tenant isolation, idempotency and audit.
 */
class AiFeedbackTest extends AiTestCase
{
    protected AiConversationService $conversations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conversations = app(AiConversationService::class);
    }

    /**
     * A conversation owned by $user with one question and one AI answer.
     *
     * @return array{0: AiConversation, 1: AiMessage}
     */
    protected function exchange(User $user, ?Organization $organization = null, string $answer = 'You can open the Loans module to review your balance.'): array
    {
        $conversation = $this->conversations->create(
            user: $user,
            organizationId: $organization?->id,
        );

        $this->conversations->appendMessage($conversation, AiMessageRole::User, 'How do I check my loan balance?');
        $message = $this->conversations->appendMessage(
            $conversation,
            AiMessageRole::Assistant,
            $answer,
            ['provider_message_id' => 'fake-1', 'model' => 'gpt-fake-feedback'],
        );

        return [$conversation, $message];
    }

    protected function submit(int $messageId, string $type = 'positive', array $extra = []): TestResponse
    {
        return $this->postJson(route('ai.feedback.store'), array_merge([
            'message_id' => $messageId,
            'type' => $type,
        ], $extra));
    }

    public function test_authenticated_user_can_submit_positive_feedback(): void
    {
        $org = $this->makeOrganization();
        $user = $this->staff($org);
        [, $message] = $this->exchange($user, $org);

        $this->actingAs($user)
            ->submit($message->id, 'positive')
            ->assertCreated()
            ->assertJsonPath('data.type', 'positive')
            ->assertJsonPath('data.message_id', $message->id);

        $this->assertDatabaseHas('ai_feedback', [
            'ai_message_id' => $message->id,
            'user_id' => $user->id,
            'type' => 'positive',
        ]);
    }

    public function test_correction_feedback_stores_the_correction(): void
    {
        $org = $this->makeOrganization();
        $user = $this->staff($org);
        [, $message] = $this->exchange($user, $org);

        $this->actingAs($user)
            ->submit($message->id, 'correction', ['correction' => 'The answer should point to the dashboard instead.'])
            ->assertCreated()
            ->assertJsonPath('data.correction', 'The answer should point to the dashboard instead.');
    }

    public function test_unauthenticated_user_cannot_submit_feedback(): void
    {
        $org = $this->makeOrganization();
        $user = $this->staff($org);
        [, $message] = $this->exchange($user, $org);

        $this->postJson(route('ai.feedback.store'), [
            'message_id' => $message->id,
            'type' => 'positive',
        ])->assertUnauthorized();
    }

    public function test_user_without_feedback_permission_cannot_submit(): void
    {
        $org = $this->makeOrganization();
        $user = $this->user();
        $user->organizations()->attach($org->id);
        [, $message] = $this->exchange($user, $org);

        $this->actingAs($user)
            ->submit($message->id, 'positive')
            ->assertForbidden();
    }

    public function test_feedback_on_another_users_private_conversation_is_denied(): void
    {
        $org = $this->makeOrganization();
        $owner = $this->vicobaUser($org, $this->member($org));
        $other = $this->vicobaUser($org, $this->member($org));

        [, $message] = $this->exchange($owner, $org);

        $this->actingAs($other)
            ->submit($message->id, 'negative')
            ->assertForbidden();
    }

    public function test_feedback_across_organizations_is_denied(): void
    {
        $orgA = $this->makeOrganization();
        $orgB = $this->makeOrganization();

        $ownerA = $this->staff($orgA);
        $staffB = $this->staff($orgB);

        [, $message] = $this->exchange($ownerA, $orgA);

        $this->actingAs($staffB)
            ->submit($message->id, 'positive')
            ->assertForbidden();
    }

    public function test_feedback_on_a_user_prompt_is_rejected(): void
    {
        $org = $this->makeOrganization();
        $user = $this->staff($org);
        [$conversation] = $this->exchange($user, $org);

        $prompt = $conversation->messages()->where('role', 'user')->firstOrFail();

        $this->actingAs($user)
            ->submit($prompt->id, 'positive')
            ->assertStatus(422);
    }

    public function test_feedback_on_an_unknown_message_is_not_found(): void
    {
        $org = $this->makeOrganization();
        $user = $this->staff($org);

        $this->actingAs($user)
            ->submit(999999, 'positive')
            ->assertNotFound();
    }

    public function test_invalid_feedback_type_is_rejected(): void
    {
        $org = $this->makeOrganization();
        $user = $this->staff($org);
        [, $message] = $this->exchange($user, $org);

        $this->actingAs($user)
            ->submit($message->id, 'excellent')
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');

        $this->assertDatabaseCount('ai_feedback', 0);
    }

    public function test_correction_length_is_validated(): void
    {
        $org = $this->makeOrganization();
        $user = $this->staff($org);
        [, $message] = $this->exchange($user, $org);

        $this->actingAs($user)
            ->submit($message->id, 'correction', ['correction' => str_repeat('a', 2001)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('correction');
    }

    public function test_reason_length_is_validated(): void
    {
        $org = $this->makeOrganization();
        $user = $this->staff($org);
        [, $message] = $this->exchange($user, $org);

        $this->actingAs($user)
            ->submit($message->id, 'negative', ['reason' => str_repeat('b', 501)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    public function test_repeated_submission_updates_instead_of_duplicating(): void
    {
        $org = $this->makeOrganization();
        $user = $this->staff($org);
        [, $message] = $this->exchange($user, $org);

        $this->actingAs($user)->submit($message->id, 'positive')->assertCreated();

        $this->actingAs($user)
            ->submit($message->id, 'negative')
            ->assertOk()
            ->assertJsonPath('data.type', 'negative');

        $this->assertDatabaseCount('ai_feedback', 1);
        $this->assertDatabaseHas('ai_feedback', [
            'ai_message_id' => $message->id,
            'user_id' => $user->id,
            'type' => 'negative',
        ]);
    }

    public function test_tenant_values_are_derived_server_side_not_from_the_request(): void
    {
        $org = $this->makeOrganization();
        $otherOrg = $this->makeOrganization();
        $user = $this->staff($org);
        [, $message] = $this->exchange($user, $org);

        $this->actingAs($user)
            ->postJson(route('ai.feedback.store'), [
                'message_id' => $message->id,
                'type' => 'positive',
                'organization_id' => $otherOrg->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('organization_id');

        $this->assertDatabaseCount('ai_feedback', 0);
    }

    public function test_client_supplied_user_id_is_rejected(): void
    {
        $org = $this->makeOrganization();
        $user = $this->staff($org);
        $other = $this->staff($org);
        [, $message] = $this->exchange($user, $org);

        $this->actingAs($user)
            ->postJson(route('ai.feedback.store'), [
                'message_id' => $message->id,
                'type' => 'positive',
                'user_id' => $other->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('user_id');

        $this->assertDatabaseCount('ai_feedback', 0);
    }

    public function test_feedback_creation_is_audited(): void
    {
        $org = $this->makeOrganization();
        $user = $this->staff($org);
        [, $message] = $this->exchange($user, $org);

        $this->actingAs($user)->submit($message->id, 'correction', [
            'correction' => 'This is a private correction body.',
        ])->assertCreated();

        $log = AuditLog::where('event', 'ai.feedback.created')->latest('id')->firstOrFail();

        $this->assertSame($message->id, (int) $log->new_values['message_id']);
        $this->assertSame('correction', $log->new_values['type']);
        $this->assertSame($org->id, (int) $log->organization_id);

        // The audit trail stores identifiers and status only — never the text.
        $this->assertStringNotContainsString(
            'This is a private correction body.',
            json_encode($log->new_values),
        );
    }

    public function test_feedback_update_is_audited(): void
    {
        $org = $this->makeOrganization();
        $user = $this->staff($org);
        [, $message] = $this->exchange($user, $org);

        $this->actingAs($user)->submit($message->id, 'positive')->assertCreated();
        $this->actingAs($user)->submit($message->id, 'negative')->assertOk();

        $this->assertDatabaseHas('audit_logs', ['event' => 'ai.feedback.updated']);
    }

    public function test_user_can_list_and_read_their_own_feedback(): void
    {
        $org = $this->makeOrganization();
        $user = $this->staff($org);
        [, $message] = $this->exchange($user, $org);

        $this->actingAs($user)->submit($message->id, 'positive')->assertCreated();

        $feedback = AiFeedback::firstOrFail();

        $this->actingAs($user)
            ->getJson(route('ai.feedback.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($user)
            ->getJson(route('ai.feedback.show', $feedback))
            ->assertOk()
            ->assertJsonPath('data.id', $feedback->id);
    }

    public function test_user_cannot_read_another_users_feedback(): void
    {
        $orgA = $this->makeOrganization();
        $owner = $this->staff($orgA);
        $other = $this->staff($orgA);
        [, $message] = $this->exchange($owner, $orgA);

        $this->actingAs($owner)->submit($message->id, 'positive')->assertCreated();

        $feedback = AiFeedback::firstOrFail();

        $this->actingAs($other)
            ->getJson(route('ai.feedback.show', $feedback))
            ->assertForbidden();
    }

    public function test_user_can_withdraw_own_feedback(): void
    {
        $org = $this->makeOrganization();
        $user = $this->staff($org);
        [, $message] = $this->exchange($user, $org);

        $this->actingAs($user)->submit($message->id, 'negative')->assertCreated();
        $feedback = AiFeedback::firstOrFail();

        $this->actingAs($user)
            ->postJson(route('ai.feedback.withdraw', $feedback))
            ->assertOk()
            ->assertJsonPath('data.status', AiFeedbackStatus::Withdrawn->value);

        $this->assertDatabaseHas('audit_logs', ['event' => 'ai.feedback.deleted']);
    }

    public function test_user_cannot_withdraw_another_users_feedback(): void
    {
        $org = $this->makeOrganization();
        $owner = $this->staff($org);
        $other = $this->staff($org);
        [, $message] = $this->exchange($owner, $org);

        $this->actingAs($owner)->submit($message->id, 'positive')->assertCreated();
        $feedback = AiFeedback::firstOrFail();

        $this->actingAs($other)
            ->postJson(route('ai.feedback.withdraw', $feedback))
            ->assertForbidden();

        $this->assertDatabaseHas('ai_feedback', [
            'id' => $feedback->id,
            'status' => AiFeedbackStatus::Submitted->value,
        ]);
    }

    public function test_withdrawn_feedback_is_not_open_for_review(): void
    {
        $org = $this->makeOrganization();
        $user = $this->staff($org);
        [, $message] = $this->exchange($user, $org);

        $this->actingAs($user)->submit($message->id, 'negative')->assertCreated();
        $feedback = AiFeedback::firstOrFail();
        $this->actingAs($user)->postJson(route('ai.feedback.withdraw', $feedback))->assertOk();

        $reviewer = $this->staff($org, 'Organization Administrator');

        $this->actingAs($reviewer)
            ->getJson(route('ai.evaluations.index'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_model_version_linkage_is_recorded_when_known(): void
    {
        $org = $this->makeOrganization();
        $user = $this->staff($org);
        [$conversation, $message] = $this->exchange($user, $org);

        $conversation->update(['provider' => 'fake']);

        $version = \App\Models\AiModelVersion::factory()->create([
            'provider' => 'fake',
            'model' => $message->model,
        ]);

        $this->actingAs($user)->submit($message->id, 'positive')->assertCreated();

        $this->assertSame($version->id, AiFeedback::firstOrFail()->ai_model_version_id);
    }

    public function test_positive_feedback_drops_an_unrelated_correction(): void
    {
        $org = $this->makeOrganization();
        $user = $this->staff($org);
        [, $message] = $this->exchange($user, $org);

        $this->actingAs($user)
            ->submit($message->id, 'correction', ['correction' => 'Some correction.'])
            ->assertCreated();

        $this->actingAs($user)->submit($message->id, 'positive')->assertOk();

        $this->assertNull(AiFeedback::firstOrFail()->correction);
    }

    public function test_feedback_type_enum_values_are_the_controlled_set(): void
    {
        $this->assertSame(
            ['positive', 'negative', 'correction'],
            AiFeedbackType::values(),
        );
    }
}
