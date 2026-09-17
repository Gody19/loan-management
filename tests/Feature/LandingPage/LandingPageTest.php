<?php

namespace Tests\Feature\LandingPage;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    // ==================== Public Access Tests ====================

    public function test_landing_page_returns_200(): void
    {
        $response = $this->get(route('home'));

        $response->assertStatus(200);
    }

    public function test_landing_page_contains_hero_section(): void
    {
        $response = $this->get(route('home'));

        $response->assertStatus(200);
        $response->assertSee('Manage Your VICOBA');
        $response->assertSee('Simplify Your Financial Operations');
    }

    public function test_landing_page_contains_features_section(): void
    {
        $response = $this->get(route('home'));

        $response->assertStatus(200);
        $response->assertSee('Platform Features');
        $response->assertSee('VICOBA Management');
        $response->assertSee('Member Management');
        $response->assertSee('Savings Management');
        $response->assertSee('Loan Management');
    }

    public function test_landing_page_contains_how_it_works_section(): void
    {
        $response = $this->get(route('home'));

        $response->assertStatus(200);
        $response->assertSee('How It Works');
        $response->assertSee('Register Your Organization');
        $response->assertSee('Set Up Your Organization');
    }

    public function test_landing_page_contains_about_section(): void
    {
        $response = $this->get(route('home'));

        $response->assertStatus(200);
        $response->assertSee('About the Platform');
    }

    public function test_landing_page_contains_contact_section(): void
    {
        $response = $this->get(route('home'));

        $response->assertStatus(200);
        $response->assertSee('Get in Touch');
        $response->assertSee('contact_name');
        $response->assertSee('contact_email');
        $response->assertSee('contact_message');
    }

    public function test_landing_page_contains_cta_section(): void
    {
        $response = $this->get(route('home'));

        $response->assertStatus(200);
        $response->assertSee('Ready to Digitize Your VICOBA Operations?');
    }

    public function test_landing_page_contains_security_section(): void
    {
        $response = $this->get(route('home'));

        $response->assertStatus(200);
        $response->assertSee('Built with Trust');
        $response->assertSee('Role-Based Access');
        $response->assertSee('Organization Isolation');
        $response->assertSee('Financial Integrity');
    }

    public function test_landing_page_contains_who_is_it_for_section(): void
    {
        $response = $this->get(route('home'));

        $response->assertStatus(200);
        $response->assertSee('Who Is It For?');
        $response->assertSee('VICOBA Organizations');
        $response->assertSee('Microfinance Organizations');
    }

    // ==================== Navigation Tests ====================

    public function test_landing_page_links_to_login(): void
    {
        $response = $this->get(route('home'));

        $response->assertStatus(200);
        $response->assertSee(route('login'));
    }

    public function test_landing_page_links_to_register_organization(): void
    {
        $response = $this->get(route('home'));

        $response->assertStatus(200);
        $response->assertSee(route('register-organization'));
    }

    public function test_landing_page_has_footer(): void
    {
        $response = $this->get(route('home'));

        $response->assertStatus(200);
        $response->assertSee('FinancePro');
        $response->assertSee(date('Y'));
    }

    // ==================== SEO Tests ====================

    public function test_landing_page_has_seo_meta(): void
    {
        $response = $this->get(route('home'));

        $response->assertStatus(200);
        $response->assertSee('VICOBA & Microfinance Management Platform');
        $response->assertSee('meta name="description"', false);
    }

    // ==================== Organization Registration Tests ====================

    public function test_register_organization_page_returns_200(): void
    {
        $response = $this->get(route('register-organization'));

        $response->assertStatus(200);
    }

    public function test_register_organization_page_has_form(): void
    {
        $response = $this->get(route('register-organization'));

        $response->assertStatus(200);
        $response->assertSee('Register Your Organization');
        $response->assertSee('name="name"', false);
        $response->assertSee('name="admin_name"', false);
        $response->assertSee('name="admin_email"', false);
        $response->assertSee('name="admin_password"', false);
        $response->assertSee('action="' . route('register-organization.store') . '"', false);
    }

    public function test_register_organization_page_links_to_login(): void
    {
        $response = $this->get(route('register-organization'));

        $response->assertStatus(200);
        $response->assertSee(route('login'));
    }

    public function test_register_organization_page_links_to_home(): void
    {
        $response = $this->get(route('register-organization'));

        $response->assertStatus(200);
        $response->assertSee(route('home'));
    }

    // ==================== Authenticated User Tests ====================

    public function test_authenticated_user_redirected_from_home(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('home'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_authenticated_member_redirected_from_home(): void
    {
        $this->createRoles();

        $user = User::factory()->create([
            'status' => UserStatus::Active,
            'is_active' => true,
        ]);
        $user->assignRole('VICOBA Member');

        $member = \App\Models\Member::factory()->create([
            'user_id' => $user->id,
            'membership_status' => 'active',
        ]);

        $this->actingAs($user);

        $response = $this->get(route('home'));

        $response->assertRedirect(route('member.dashboard'));
    }

    public function test_authenticated_user_redirected_from_registration(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('register-organization'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_unauthenticated_user_can_access_registration(): void
    {
        $response = $this->get(route('register-organization'));

        $response->assertStatus(200);
    }

    // ==================== Existing Functionality Tests ====================

    public function test_login_route_still_works(): void
    {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
    }

    public function test_dashboard_requires_auth(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_member_dashboard_requires_auth(): void
    {
        $response = $this->get(route('member.dashboard'));

        $response->assertRedirect(route('login'));
    }

    // ==================== Registration Validation Tests ====================

    public function test_registration_requires_name(): void
    {
        $response = $this->post(route('register-organization.store'), []);

        $response->assertSessionHasErrors('name');
    }

    public function test_registration_requires_admin_email(): void
    {
        $response = $this->post(route('register-organization.store'), [
            'name' => 'Test Org',
            'phone' => '0712345678',
            'email' => 'org@test.com',
            'admin_name' => 'Admin User',
            'admin_phone' => '0712345678',
        ]);

        $response->assertSessionHasErrors('admin_email');
    }

    public function test_registration_requires_admin_password(): void
    {
        $response = $this->post(route('register-organization.store'), [
            'name' => 'Test Org',
            'phone' => '0712345678',
            'email' => 'org@test.com',
            'admin_name' => 'Admin User',
            'admin_email' => 'admin@test.com',
            'admin_phone' => '0712345678',
        ]);

        $response->assertSessionHasErrors('admin_password');
    }

    public function test_registration_validates_password_confirmation(): void
    {
        $response = $this->post(route('register-organization.store'), [
            'name' => 'Test Org',
            'phone' => '0712345678',
            'email' => 'org@test.com',
            'admin_name' => 'Admin User',
            'admin_email' => 'admin@test.com',
            'admin_phone' => '0712345678',
            'admin_password' => 'password',
            'admin_password_confirmation' => 'different',
        ]);

        $response->assertSessionHasErrors('admin_password');
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        \App\Models\Organization::factory()->create(['email' => 'existing@test.com']);

        $response = $this->post(route('register-organization.store'), [
            'name' => 'Test Org',
            'phone' => '0712345678',
            'email' => 'existing@test.com',
            'admin_name' => 'Admin User',
            'admin_email' => 'admin@test.com',
            'admin_phone' => '0712345678',
            'admin_password' => 'password',
            'admin_password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_registration_rejects_duplicate_admin_email(): void
    {
        User::factory()->create(['email' => 'existing-admin@test.com']);

        $response = $this->post(route('register-organization.store'), [
            'name' => 'Test Org',
            'phone' => '0712345678',
            'email' => 'org@test.com',
            'admin_name' => 'Admin User',
            'admin_email' => 'existing-admin@test.com',
            'admin_phone' => '0712345678',
            'admin_password' => 'password',
            'admin_password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('admin_email');
    }

    public function test_successful_registration_creates_organization_and_user(): void
    {
        $this->createRoles();

        $response = $this->post(route('register-organization.store'), [
            'name' => 'Test VICOBA Org',
            'registration_number' => 'REG-001',
            'phone' => '0712345678',
            'email' => 'test-org@test.com',
            'region' => 'Dar es Salaam',
            'district' => 'Kinondoni',
            'address' => '123 Main Street',
            'admin_name' => 'John Admin',
            'admin_email' => 'john@test.com',
            'admin_phone' => '0712345679',
            'admin_password' => 'password',
            'admin_password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('organizations', [
            'name' => 'Test VICOBA Org',
            'email' => 'test-org@test.com',
        ]);

        $this->assertDatabaseHas('users', [
            'fullname' => 'John Admin',
            'email' => 'john@test.com',
        ]);
    }

    public function test_successful_registration_assigns_admin_role(): void
    {
        $this->createRoles();

        $this->post(route('register-organization.store'), [
            'name' => 'Test VICOBA Org',
            'registration_number' => 'REG-001',
            'phone' => '0712345678',
            'email' => 'test-org@test.com',
            'admin_name' => 'John Admin',
            'admin_email' => 'john@test.com',
            'admin_phone' => '0712345679',
            'admin_password' => 'password',
            'admin_password_confirmation' => 'password',
        ]);

        $user = \App\Models\User::where('email', 'john@test.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('Organization Administrator'));
    }

    public function test_successful_registration_attaches_user_to_organization(): void
    {
        $this->createRoles();

        $this->post(route('register-organization.store'), [
            'name' => 'Test VICOBA Org',
            'registration_number' => 'REG-002',
            'phone' => '0712345678',
            'email' => 'test-org@test.com',
            'admin_name' => 'John Admin',
            'admin_email' => 'john@test.com',
            'admin_phone' => '0712345679',
            'admin_password' => 'password',
            'admin_password_confirmation' => 'password',
        ]);

        $org = \App\Models\Organization::where('email', 'test-org@test.com')->first();
        $user = \App\Models\User::where('email', 'john@test.com')->first();

        $this->assertNotNull($org);
        $this->assertNotNull($user);
        $this->assertTrue($org->users->contains($user));
    }

    private function createRoles(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Super Administrator', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'VICOBA Member', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Organization Administrator', 'guard_name' => 'web']);
    }
}
