<?php

namespace Tests\Unit;

use App\AI\Contracts\AiToolInterface;
use App\AI\Policies\AiToolPolicy;
use App\AI\Services\AiToolRegistry;
use App\AI\Tools\LoanViewTool;
use App\AI\Tools\MemberViewTool;
use App\AI\Tools\NullTool;
use PHPUnit\Framework\TestCase;

class AiToolRegistryTest extends TestCase
{
    private AiToolRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new AiToolRegistry;
    }

    public function test_registry_exposes_registered_capabilities(): void
    {
        $capabilities = $this->registry->registeredCapabilities();

        foreach ($capabilities as $capability) {
            $definition = $this->registry->definition($capability);

            $this->assertNotNull($definition);
            $this->assertSame($capability, $capability);
            $this->assertArrayHasKey('handler', $definition);
        }
    }

    public function test_business_capabilities_exclude_null_handlers(): void
    {
        $definitions = $this->registry->businessCapabilities();

        foreach ($definitions as $definition) {
            $this->assertNotSame(NullTool::class, $definition['handler']);
        }

        $this->assertSame(
            21,
            count($this->registry->businessCapabilities()),
        );
    }

    public function test_every_business_handler_exists_and_implements_contract(): void
    {
        foreach ($this->registry->businessCapabilities() as $definition) {
            $handler = $definition['handler'];

            $this->assertTrue(class_exists($handler), "Handler {$handler} does not exist.");
            $this->assertTrue(
                is_subclass_of($handler, AiToolInterface::class),
                "Handler {$handler} does not implement AiToolInterface.",
            );
        }
    }

    public function test_every_business_handler_registered_exactly_once(): void
    {
        $handlers = array_column($this->registry->businessCapabilities(), 'handler');

        $this->assertSame(count($handlers), count(array_unique($handlers)));
    }

    public function test_every_business_capability_has_description(): void
    {
        foreach ($this->registry->businessCapabilities() as $definition) {
            $this->assertNotSame('', $definition['description']);
        }
    }

    public function test_business_capabilities_are_user_org_scoped(): void
    {
        foreach ($this->registry->businessCapabilities() as $definition) {
            $this->assertSame(
                AiToolRegistry::SCOPE_USER_ORG,
                $definition['scope'],
            );
        }
    }

    public function test_argument_schemas_use_approved_rule_types_only(): void
    {
        $approved = ['string', 'integer', 'float'];

        foreach ($this->registry->registeredCapabilities() as $capability) {
            foreach ($this->registry->argumentRules($capability) as $argument => $type) {
                $this->assertContains($type, $approved, "Argument {$capability}.{$argument} uses an unapproved rule type.");
            }
        }
    }

    public function test_no_capability_accepts_forbidden_tenant_keys(): void
    {
        foreach ($this->registry->registeredCapabilities() as $capability) {
            foreach (array_keys($this->registry->argumentRules($capability)) as $argument) {
                $this->assertNotContains(
                    $argument,
                    AiToolPolicy::FORBIDDEN_ARGUMENT_KEYS,
                    "Capability {$capability} exposes forbidden argument key {$argument}.",
                );
            }
        }
    }

    public function test_no_capability_denies_a_real_class_path_or_sql(): void
    {
        foreach ([
            MemberViewTool::class,
            LoanViewTool::class,
            'App\\AI\\Services\\AiToolRunnerService',
            'select * from loans',
            'exec()',
        ] as $bogus) {
            $this->assertFalse($this->registry->has($bogus));
        }
    }

    public function test_registered_handler_is_never_a_class_string_resolvable_from_input(): void
    {
        $handlers = array_column($this->registry->businessCapabilities(), 'handler');

        foreach ($handlers as $handler) {
            $this->assertFalse($this->registry->has($handler));
        }
    }
}
