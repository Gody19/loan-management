<?php

namespace Tests\Feature\AI;

use App\AI\Exceptions\AiUnavailableException;
use App\Enums\UserStatus;
use App\Models\AiConversation;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Phase 11.4 chat UI surface: server-rendered chat page, role-aware layouts,
 * navigation visibility, safe markup, authorization fences, and rate limiting.
 * The page is an inert presentation shell — authorization and tenant scoping
 * remain server-side, and the client never exposes internals.
 */
class AiChatUiTest extends AiTestCase
{
    private function roleWith(array $permissions): Role
    {
        $role = Role::firstOrCreate(['name' => Str::random(8), 'guard_name' => 'web']);
        $role->syncPermissions($permissions);

        return $role;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/ai')->assertRedirect(route('login'));
    }

    public function test_user_without_ai_view_permission_cannot_open_page(): void
    {
        $this->actingAs($this->user());

        $this->get('/ai')->assertStatus(403);
    }

    public function test_user_without_ai_use_permission_cannot_chat(): void
    {
        $user = $this->user($this->roleWith(['ai.view'])->name);
        $this->actingAs($user);

        $this->get('/ai')->assertOk();
        $this->postJson('/ai/chat', ['message' => 'Hello'])->assertStatus(403);
    }

    public function test_suspended_user_is_sent_back_to_login(): void
    {
        $user = $this->user('Loan Officer');
        $user->update(['status' => UserStatus::Suspended]);
        $this->actingAs($user);

        $this->get('/ai')->assertRedirect(route('login'));
    }

    public function test_member_pages_render_the_member_portal_layout(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);
        $user = $this->vicobaUser($org, $member);
        $this->actingAs($user);

        $response = $this->get('/ai');

        $response->assertOk();
        $this->assertSame('ai.member', $response->original->name());
        $this->assertContains('What is my current loan balance?', $response->viewData('suggestions'));
        $this->assertContains('What are my current savings?', $response->viewData('suggestions'));
        $this->assertContains('What are my current shares?', $response->viewData('suggestions'));

        $html = $response->getContent();
        $this->assertStringContainsString('Member Portal', $html);
        $this->assertStringContainsString('Ask FinancePro AI', $html);
        $this->assertStringContainsString('Your message', $html);
    }

    public function test_staff_pages_render_the_admin_layout_with_role_suggestions(): void
    {
        $org = $this->makeOrganization();
        $staff = $this->staff($org, 'Loan Officer');
        $this->actingAs($staff);

        $response = $this->get('/ai');

        $response->assertOk();
        $this->assertSame('ai.index', $response->original->name());

        $suggestions = $response->viewData('suggestions');
        $this->assertContains('How can I check my loan eligibility?', $suggestions);
        $this->assertContains('How are loan repayments scheduled?', $suggestions);
        $this->assertNotContains('What are my current savings?', $suggestions);

        $html = $response->getContent();
        $this->assertStringContainsString('VICOBA System', $html);
        $this->assertStringNotContainsString('Member Portal', $html);
    }

    public function test_chat_page_binds_a_single_composer(): void
    {
        $org = $this->makeOrganization();
        $staff = $this->staff($org, 'Loan Officer');
        $this->actingAs($staff);

        $response = $this->get('/ai')->assertOk();

        // The floating widget and the full chat page both embed the same chat
        // partial. The widget must be suppressed on the dedicated chat page so
        // the composer and its script exist exactly once — otherwise every
        // message would be sent twice (duplicate bubbles and responses).
        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'id="aiComposer"'));
        $this->assertStringNotContainsString('aiWidgetLauncher', $html);
    }

    public function test_treasurer_staff_sees_generic_suggestions_only(): void
    {
        $org = $this->makeOrganization();
        $treasurer = $this->user('Treasurer');
        $treasurer->organizations()->attach($org->id);
        $this->actingAs($treasurer);

        $response = $this->get('/ai');

        $response->assertOk();
        $suggestions = $response->viewData('suggestions');

        $this->assertContains('What can you help me with?', $suggestions);
        $this->assertNotContains('What are my current savings?', $suggestions);
        $this->assertNotContains('Show me my recent repayment information.', $suggestions);
    }

    public function test_page_markup_exposes_no_internals(): void
    {
        $org = $this->makeOrganization();
        $this->actingAs($this->staff($org, 'Branch Manager'));

        $html = $this->get('/ai')->assertOk()->getContent();

        foreach ([
            'ai.member.loans',
            'ai.member.financial_summary',
            'ai.loan.repayments',
            'FINANCEPRO_DATUM',
            'system_instructions',
            'OPENAI_API_KEY',
            'AI_CHAT_RATE_LIMIT',
            'is_super_admin',
            'shell_exec',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $html, "Page must not leak [{$needle}].");
        }

        $this->assertStringContainsString('textContent', $html);
        $this->assertStringNotContainsString('innerHTML', $html);
    }

    public function test_chat_ui_wires_to_json_endpoints_with_csrf(): void
    {
        $org = $this->makeOrganization();
        $this->actingAs($this->staff($org, 'Loan Officer'));

        $html = $this->get('/ai')->assertOk()->getContent();

        // @json() renders URLs with escaped slashes (e.g. "http:\/\/localhost\/ai\/chat").
        $this->assertStringContainsString('\/ai\/chat', $html);
        $this->assertStringContainsString('\/ai\/conversations', $html);
        $this->assertStringContainsString('X-CSRF-TOKEN', $html);
        $this->assertStringContainsString('textContent = text', $html);
    }

    public function test_chat_history_is_not_a_nested_scroll_region(): void
    {
        $org = $this->makeOrganization();
        $this->actingAs($this->staff($org, 'Loan Officer'));

        $html = $this->get('/ai')->assertOk()->getContent();

        // The sidebar history grows with the page instead of scrolling inside
        // its own 60vh box, so the "..." dropdown is never clipped by it.
        $this->assertStringContainsString('.ai-conv-list {', $html);
        $this->assertStringNotContainsString('.ai-conv-list {
            max-height: 60vh;', $html);

        // The message thread stays scrollable but renders no scrollbar chrome,
        // via the existing .no-scrollbar utility.
        $this->assertStringContainsString('class="card-body ai-messages no-scrollbar"', $html);
    }

    public function test_options_menu_is_pinned_to_one_fixed_spot(): void
    {
        $org = $this->makeOrganization();
        $this->actingAs($this->staff($org, 'Loan Officer'));

        $html = $this->get('/ai')->assertOk()->getContent();

        // The title must be able to shrink and truncate, otherwise a long chat
        // title pushes the "..." trigger sideways.
        $this->assertStringContainsString(
            '.ai-conv-row .ai-conv-item { flex: 1 1 0; min-width: 0;',
            $html,
        );

        // The trigger occupies a fixed-width slot and is centred in it, so the
        // dots always sit in the same place regardless of title length.
        $this->assertStringContainsString('.ai-conv-menu { position: relative; flex: 0 0 auto; width: 2rem; }', $html);
        $this->assertStringContainsString('justify-content: center;', $html);

        // Revealed on row hover / keyboard focus, and always visible on touch.
        $this->assertStringContainsString('.ai-conv-row:hover .ai-conv-menu-trigger', $html);
        $this->assertStringContainsString('@media (hover: none)', $html);
    }

    public function test_conversation_row_uses_an_options_menu_instead_of_a_delete_button(): void
    {
        $org = $this->makeOrganization();
        $this->actingAs($this->staff($org, 'Loan Officer'));

        $html = $this->get('/ai')->assertOk()->getContent();

        // ChatGPT-style per-chat "..." trigger, hidden until hover/focus.
        $this->assertStringContainsString('ai-conv-menu-trigger', $html);
        $this->assertStringContainsString('bi-three-dots-vertical', $html);
        $this->assertStringContainsString("setAttribute('aria-haspopup', 'true')", $html);
        $this->assertStringContainsString("setAttribute('aria-expanded', 'false')", $html);
        $this->assertStringContainsString("setAttribute('role', 'menu')", $html);

        // The menu is built on demand and closed by Escape or an outside click.
        $this->assertStringContainsString('.ai-conv-menu-panel[hidden] { display: none; }', $html);
        $this->assertStringContainsString("event.key === 'Escape'", $html);
        $this->assertStringContainsString('closeAnyOpenMenu(null);', $html);

        // The old always-visible trash button is gone.
        $this->assertStringNotContainsString('ai-conv-delete', $html);

        // Built with createElement/textContent only, so a conversation title
        // can never inject markup into the list.
        $this->assertStringNotContainsString('innerHTML', $html);
    }

    public function test_options_menu_exposes_rename_and_delete_actions(): void
    {
        $org = $this->makeOrganization();
        $this->actingAs($this->staff($org, 'Loan Officer'));

        $html = $this->get('/ai')->assertOk()->getContent();

        $this->assertStringContainsString("addEl(panel, 'button', 'ai-conv-menu-item', 'Rename')", $html);
        $this->assertStringContainsString('ai-conv-menu-danger', $html);
        $this->assertStringContainsString('Delete chat', $html);
        $this->assertStringContainsString('renameConversation(item);', $html);
        $this->assertStringContainsString('deleteConversation(item.id);', $html);
    }

    public function test_chat_ui_wires_the_rename_endpoint(): void
    {
        $org = $this->makeOrganization();
        $this->actingAs($this->staff($org, 'Loan Officer'));

        $html = $this->get('/ai')->assertOk()->getContent();

        $this->assertStringContainsString('updateTmpl:', $html);
        $this->assertStringContainsString("method: 'PATCH'", $html);
        $this->assertStringContainsString('body: JSON.stringify({ title: next })', $html);
    }

    public function test_conversation_list_page_renders_empty_state_for_new_member(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);
        $user = $this->vicobaUser($org, $member);
        $this->actingAs($user);

        $this->assertSame(0, AiConversation::count());

        $response = $this->get('/ai');
        $response->assertOk();
        $this->assertStringContainsString('Ask FinancePro AI', $response->getContent());

        $this->getJson('/ai/conversations')
            ->assertOk()
            ->assertJson(['data' => []]);
    }

    public function test_member_conversation_list_only_shows_own_conversations(): void
    {
        $org = $this->makeOrganization();
        $memberA = $this->member($org);
        $userA = $this->vicobaUser($org, $memberA);
        AiConversation::factory()->create(['user_id' => $userA->id, 'organization_id' => $org->id, 'title' => 'Mine']);

        $memberB = $this->member($org);
        $userB = $this->vicobaUser($org, $memberB);
        AiConversation::factory()->create(['user_id' => $userB->id, 'organization_id' => $org->id, 'title' => 'Peer']);

        $this->actingAs($userA);

        $this->getJson('/ai/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['title' => 'Mine'])
            ->assertJsonMissing(['title' => 'Peer']);

        $this->getJson('/ai/conversations/'.AiConversation::where('title', 'Peer')->first()->id)
            ->assertStatus(403);
    }

    public function test_nav_link_is_visible_to_users_with_ai_use(): void
    {
        $org = $this->makeOrganization();
        $this->actingAs($this->staff($org, 'Loan Officer'));

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('AI Assistant')
            ->assertSee(route('ai.index'), false);
    }

    public function test_member_portal_nav_shows_the_ai_assistant_link(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);
        $this->actingAs($this->vicobaUser($org, $member));

        $this->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee('AI Assistant')
            ->assertSee(route('ai.index'), false);
    }

    public function test_nav_link_is_hidden_from_users_without_ai_use(): void
    {
        $role = Role::firstOrCreate(['name' => Str::random(8), 'guard_name' => 'web']);
        $role->syncPermissions(['dashboard.view']);

        $this->actingAs($this->user($role->name));

        $this->get('/dashboard')
            ->assertOk()
            ->assertDontSee('AI Assistant');
    }

    public function test_chat_endpoint_is_rate_limited(): void
    {
        config(['ai.chat_rate_limit' => 1]);

        $this->actingAs($this->superAdmin());

        $this->postJson('/ai/chat', ['message' => 'Hello one'])->assertOk();
        $this->postJson('/ai/chat', ['message' => 'Hello two'])->assertStatus(429);
    }

    public function test_tool_endpoint_is_rate_limited(): void
    {
        config(['ai.tool_rate_limit' => 1]);

        $org = $this->makeOrganization();
        $staff = $this->staff($org, 'Loan Officer');
        $member = $this->member($org);

        $this->actingAs($staff);

        $payload = [
            'capability' => 'ai.member.view',
            'arguments' => ['member_number' => $member->member_number],
            'question' => 'Report.',
        ];

        $this->postJson('/ai/tool', $payload)->assertOk();

        $this->postJson('/ai/tool', $payload)->assertStatus(429);
    }

    public function test_page_renders_even_when_ai_is_disabled_but_chat_returns_503(): void
    {
        $this->disableAi();

        $org = $this->makeOrganization();
        $this->actingAs($this->staff($org, 'Loan Officer'));

        $this->get('/ai')->assertOk();

        $this->postJson('/ai/chat', ['message' => 'Hello'])
            ->assertStatus(503)
            ->assertJsonPath('message', AiUnavailableException::SAFE_MESSAGE);
    }
}
