<?php

namespace Tests\Feature\AI;

use App\Models\AiConversation;
use App\Models\Organization;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AiAuthorizationTest extends AiTestCase
{
    private function aiUser(): \App\Models\User
    {
        $role = Role::firstOrCreate(['name' => Str::random(8), 'guard_name' => 'web']);
        $role->syncPermissions(['ai.view', 'ai.use']);

        return $this->user($role->name);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        // Non-JSON GET follows the standard redirect to the login page ...
        $this->get('/ai/conversations')->assertRedirect(route('login'));

        // ... while an unauthenticated JSON POST is answered with 401 by the
        // authenticated middleware group (never reaches any AI code).
        $this->postJson('/ai/chat', ['message' => 'Hello'])->assertStatus(401);
    }

    public function test_user_without_permission_receives_403(): void
    {
        $this->actingAs($this->user());

        $this->get('/ai/conversations')->assertStatus(403);
        $this->postJson('/ai/chat', ['message' => 'Hello'])->assertStatus(403);
    }

    public function test_user_with_ai_view_permission_can_list_conversations(): void
    {
        $user = $this->aiUser();
        $this->actingAs($user);
        AiConversation::factory()->create(['user_id' => $user->id, 'title' => 'Mine']);

        $response = $this->getJson('/ai/conversations');

        $response->assertOk()
            ->assertJsonPath('data.0.title', 'Mine');
    }

    public function test_super_administrator_can_chat(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin);

        $response = $this->postJson('/ai/chat', ['message' => 'Hello AI']);

        $response->assertOk()
            ->assertJsonPath('data.provider', 'fake')
            ->assertJsonPath('data.conversation_id', \App\Models\AiConversation::first()->id);

        $this->assertNotEmpty($response->json('data.content'));

        $usage = $response->json('data.usage');
        $this->assertIsInt($usage['input_tokens']);
        $this->assertIsInt($usage['output_tokens']);
        $this->assertIsInt($usage['total_tokens']);
        $this->assertGreaterThan(0, $usage['total_tokens']);

        $this->assertDatabaseHas('ai_conversations', ['user_id' => $admin->id]);
    }

    public function test_ai_disabled_returns_unavailable_503(): void
    {
        $this->disableAi();
        $this->actingAs($this->superAdmin());

        $this->getJson('/ai/conversations')
            ->assertStatus(503)
            ->assertJsonPath('message', \App\AI\Exceptions\AiUnavailableException::SAFE_MESSAGE);

        $this->postJson('/ai/chat', ['message' => 'Hello'])
            ->assertStatus(503)
            ->assertJsonPath('message', \App\AI\Exceptions\AiUnavailableException::SAFE_MESSAGE);
    }

    public function test_invalid_chat_payload_is_rejected(): void
    {
        $this->actingAs($this->aiUser());

        $this->postJson('/ai/chat', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');
    }

    public function test_cross_organization_conversation_is_denied_over_http(): void
    {
        $orgA = Organization::factory()->create(['status' => 'active']);
        $orgB = Organization::factory()->create(['status' => 'active']);

        $userA = $this->aiUser();
        $userA->organizations()->attach($orgA->id);
        $this->actingAs($userA);

        $create = $this->postJson('/ai/chat', ['message' => 'Private for A']);
        $create->assertOk();
        $conversationId = $create->json('data.conversation_id');

        $userB = $this->aiUser();
        $userB->organizations()->attach($orgB->id);
        $this->actingAs($userB);

        $this->getJson("/ai/conversations/{$conversationId}")->assertStatus(403);
        $this->postJson('/ai/chat', ['message' => 'Hi again', 'conversation_id' => $conversationId])
            ->assertStatus(403);
    }

    public function test_app_remains_functional_when_ai_is_disabled(): void
    {
        $this->disableAi();
        $this->actingAs($this->superAdmin());

        $this->postJson('/ai/chat', ['message' => 'Hello'])->assertStatus(503);
        $this->get('/dashboard')->assertOk();
    }
}