<?php

namespace Tests\Unit;

use App\AI\DTOs\AiContextData;
use App\AI\Policies\AiAuthorizationCategory;
use App\AI\Policies\AiToolPolicy;
use App\AI\Services\AiToolRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AiToolPolicyTest extends TestCase
{
    private function policy(): AiToolPolicy
    {
        return new AiToolPolicy(new AiToolRegistry());
    }

    private function context(
        array $roles = [],
        array $permissions = [],
    ): AiContextData {
        return new AiContextData(
            userId: 1,
            isSuperAdmin: in_array('Super Administrator', $roles, true),
            roles: $roles,
            permissions: $permissions,
            organizationIds: [10],
            branchIds: [20],
            vicobaGroupIds: [],
            memberId: null,
        );
    }

    public function test_unknown_capability_is_denied_by_default(): void
    {
        $decision = $this->policy()->evaluate(
            $this->context(),
            'ai.financial.report.summarize',
            [],
        );

        $this->assertFalse($decision['allowed']);
        $this->assertSame(AiAuthorizationCategory::UnknownCapability->value, $decision['reason']);
    }

    public function test_malformed_capability_is_denied(): void
    {
        $this->assertFalse($this->policy()->evaluate($this->context(), '')[ 'allowed']);
        $this->assertFalse($this->policy()->evaluate($this->context(), 'ai.chat ')[ 'allowed']);
        $this->assertFalse($this->policy()->evaluate($this->context(), 123)[ 'allowed']);
    }

    public function test_missing_permission_is_denied(): void
    {
        $decision = $this->policy()->evaluate(
            $this->context(permissions: ['ai.view']),
            'ai.chat',
            [],
        );

        $this->assertFalse($decision['allowed']);
        $this->assertSame(AiAuthorizationCategory::MissingPermission->value, $decision['reason']);
        $this->assertSame('ai.use', $decision['denied_key']);
    }

    #[DataProvider('escalationArguments')]
    public function test_scope_escalation_arguments_are_rejected(array $arguments): void
    {
        $decision = $this->policy()->evaluate(
            $this->context(permissions: ['ai.use']),
            'ai.chat',
            $arguments,
        );

        $this->assertFalse($decision['allowed']);
        $this->assertSame(AiAuthorizationCategory::EscalationAttempt->value, $decision['reason']);
    }

    public static function escalationArguments(): array
    {
        return [
            'organization' => [['organization_id' => 1]],
            'branch' => [['branch_id' => 2]],
            'vicoba group' => [['vicoba_group_id' => 3]],
            'member' => [['member_id' => 4]],
            'user' => [['user_id' => 5]],
            'role' => [['role' => 'Super Administrator']],
            'permission' => [['permission' => 'ai.use']],
            'super admin flag' => [['is_super_admin' => true]],
            'scope key' => [['scope' => 'platform']],
            'sql' => [['sql' => 'SELECT *']],
            'query' => [['query' => 'members']],
            'file' => [['file' => '/etc/passwd']],
            'command' => [['command' => 'rm -rf']],
            'class' => [['class' => 'App\\Models\\Member']],
            'callable' => [['callable' => 'exec']],
        ];
    }

    public function test_unregistered_argument_is_rejected(): void
    {
        $decision = $this->policy()->evaluate(
            $this->context(permissions: ['ai.use']),
            'ai.chat',
            ['totally_fabricated_key' => 'value'],
        );

        $this->assertFalse($decision['allowed']);
        $this->assertSame(AiAuthorizationCategory::ArgumentNotAllowed->value, $decision['reason']);
    }

    public function test_argument_type_mismatch_is_rejected(): void
    {
        $decision = $this->policy()->evaluate(
            $this->context(permissions: ['ai.use']),
            'ai.chat',
            ['conversation_id' => 'not-an-integer'],
        );

        $this->assertFalse($decision['allowed']);
        $this->assertSame(AiAuthorizationCategory::MalformedArgument->value, $decision['reason']);
    }

    public function test_valid_chat_invocation_is_allowed(): void
    {
        $decision = $this->policy()->evaluate(
            $this->context(permissions: ['ai.use']),
            'ai.chat',
            ['conversation_id' => 42],
        );

        $this->assertTrue($decision['allowed']);
        $this->assertNull($decision['reason']);
    }

    public function test_conversation_read_requires_ai_view(): void
    {
        $this->assertTrue(
            $this->policy()->evaluate(
                $this->context(permissions: ['ai.view']),
                'ai.conversation.read',
                ['conversation_id' => 7],
            )['allowed']
        );

        $this->assertFalse(
            $this->policy()->evaluate(
                $this->context(permissions: []),
                'ai.conversation.read',
                ['conversation_id' => 7],
            )['allowed']
        );
    }

    public function test_conversation_list_requires_ai_view(): void
    {
        $this->assertTrue(
            $this->policy()->evaluate(
                $this->context(permissions: ['ai.view']),
                'ai.conversation.list',
            )['allowed']
        );
    }

    public function test_super_administrator_still_needs_registration_and_permissions(): void
    {
        $this->assertFalse(
            $this->policy()->evaluate(
                $this->context(roles: ['Super Administrator']),
                'ai.chat',
                [],
            )['allowed'],
            'Super Administrator must still be gated by an explicit capability and permission.'
        );
    }
}