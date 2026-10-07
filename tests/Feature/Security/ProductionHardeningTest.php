<?php

namespace Tests\Feature\Security;

use App\Enums\Gender;
use App\Enums\Relationship;
use App\Enums\VerificationStatus;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\Member;
use App\Models\MemberDocument;
use App\Models\MemberNextOfKin;
use App\Models\Organization;
use App\Models\SavingsProduct;
use App\Models\User;
use App\Models\VicobaGroup;
use App\Services\LoanRepaymentService;
use App\Services\MemberService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression tests for the Phase 13 production hardening fixes.
 *
 * Each test here corresponds to a confirmed production defect. They are written
 * so that reverting the fix fails the test, rather than merely asserting current
 * behaviour.
 */
class ProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    private Member $memberA;

    private Member $memberB;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->orgA = Organization::factory()->create(['status' => 'active']);
        $this->orgB = Organization::factory()->create(['status' => 'active']);

        $branchA = Branch::factory()->create(['organization_id' => $this->orgA->id]);
        $branchB = Branch::factory()->create(['organization_id' => $this->orgB->id]);

        $this->memberA = Member::factory()->create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $branchA->id,
        ]);

        $this->memberB = Member::factory()->create([
            'organization_id' => $this->orgB->id,
            'branch_id' => $branchB->id,
        ]);

        $this->staff = User::factory()->create(['status' => 'active']);
        $this->staff->assignRole(Role::firstOrCreate(['name' => 'Loan Officer', 'guard_name' => 'web']));
        $this->staff->organizations()->attach($this->orgA->id);
    }

    // ===== MemberDocumentController: cross-tenant document access =====

    /**
     * The document and the member were bound independently from the URL and only
     * the member was authorized, so any document id could be paired with a member
     * the caller legitimately owns.
     */
    public function test_a_member_document_cannot_be_downloaded_through_another_members_url(): void
    {
        $document = MemberDocument::create([
            'member_id' => $this->memberB->id,
            'document_type' => 'national_id',
            'document_number' => 'B-DOC-1',
            'file_path' => 'member-documents/b.pdf',
            'original_filename' => 'b.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 10,
            'verification_status' => VerificationStatus::Pending,
        ]);

        $this->actingAs($this->staff)
            ->get("/members/{$this->memberA->id}/documents/{$document->id}/download")
            ->assertForbidden();
    }

    public function test_a_member_document_cannot_be_verified_through_another_members_url(): void
    {
        $document = MemberDocument::create([
            'member_id' => $this->memberB->id,
            'document_type' => 'national_id',
            'document_number' => 'B-DOC-2',
            'file_path' => 'member-documents/b2.pdf',
            'original_filename' => 'b2.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 10,
            'verification_status' => VerificationStatus::Pending,
        ]);

        $this->actingAs($this->staff)
            ->post("/members/{$this->memberA->id}/documents/{$document->id}/verify", [
                'verification_status' => 'verified',
            ])
            ->assertForbidden();

        $this->assertSame(VerificationStatus::Pending, $document->fresh()->verification_status);
    }

    public function test_a_member_document_cannot_be_deleted_through_another_members_url(): void
    {
        $document = MemberDocument::create([
            'member_id' => $this->memberB->id,
            'document_type' => 'national_id',
            'document_number' => 'B-DOC-3',
            'file_path' => 'member-documents/b3.pdf',
            'original_filename' => 'b3.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 10,
            'verification_status' => VerificationStatus::Pending,
        ]);

        $this->actingAs($this->staff)
            ->delete("/members/{$this->memberA->id}/documents/{$document->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('member_documents', ['id' => $document->id]);
    }

    // ===== MemberNextOfKinController: cross-tenant next-of-kin =====

    public function test_next_of_kin_cannot_be_updated_through_another_members_url(): void
    {
        $kin = MemberNextOfKin::create([
            'member_id' => $this->memberB->id,
            'full_name' => 'B Relative',
            'relationship' => Relationship::Spouse,
            'phone' => '+255700000001',
        ]);

        $this->actingAs($this->staff)
            ->put("/members/{$this->memberA->id}/next-of-kin/{$kin->id}", [
                'full_name' => 'Hijacked',
                'relationship' => Relationship::Spouse->value,
                'phone' => '+255700000001',
            ])
            ->assertForbidden();

        $this->assertSame('B Relative', $kin->fresh()->full_name);
    }

    public function test_next_of_kin_cannot_be_deleted_through_another_members_url(): void
    {
        $kin = MemberNextOfKin::create([
            'member_id' => $this->memberB->id,
            'full_name' => 'B Relative Two',
            'relationship' => Relationship::Spouse,
            'phone' => '+255700000002',
        ]);

        $this->actingAs($this->staff)
            ->delete("/members/{$this->memberA->id}/next-of-kin/{$kin->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('member_next_of_kins', ['id' => $kin->id]);
    }

    // ===== SavingsAccountController::store authorization =====

    /**
     * The create endpoint had no authorize() call and the FormRequest's
     * authorize() returned true, so any authenticated user could open a savings
     * account in any organization.
     *
     * The payload is fully valid on purpose: an incomplete body would be rejected
     * by validation before authorization ever ran, which would make the test pass
     * for the wrong reason.
     */
    private function savingsAccountPayload(Organization $organization, Member $member): array
    {
        return [
            'organization_id' => $organization->id,
            'member_id' => $member->id,
            'branch_id' => $member->branch_id,
            'savings_product_id' => SavingsProduct::factory()->create([
                'organization_id' => $organization->id,
            ])->id,
            'vicoba_group_id' => VicobaGroup::factory()->create([
                'branch_id' => $member->branch_id,
            ])->id,
            'opening_date' => now()->toDateString(),
        ];
    }

    /**
     * Isolates the PERMISSION guard: this caller is a member of org A, so the
     * organization check would pass. Only the missing permission can reject.
     */
    public function test_a_user_without_permission_cannot_create_a_savings_account(): void
    {
        $plainUser = User::factory()->create(['status' => 'active']);
        $plainUser->assignRole(Role::firstOrCreate(['name' => 'VICOBA Member', 'guard_name' => 'web']));
        $plainUser->organizations()->attach($this->orgA->id);

        $this->actingAs($plainUser)
            ->post('/savings-accounts', $this->savingsAccountPayload($this->orgA, $this->memberA))
            ->assertForbidden();
    }

    /**
     * Isolates the ORGANIZATION guard: this caller is a Treasurer with full
     * savings permissions, so only the cross-tenant check can reject.
     */
    public function test_a_savings_account_cannot_be_created_in_another_organization(): void
    {
        $treasurer = User::factory()->create(['status' => 'active']);
        $treasurer->assignRole(Role::firstOrCreate(['name' => 'Treasurer', 'guard_name' => 'web']));
        $treasurer->organizations()->attach($this->orgA->id);

        $this->actingAs($treasurer)
            ->post('/savings-accounts', $this->savingsAccountPayload($this->orgB, $this->memberB))
            ->assertForbidden();
    }

    public function test_a_permitted_user_can_still_create_a_savings_account_in_their_own_organization(): void
    {
        // Only roles that actually hold savings_account.create should succeed.
        // This guards against the fix collapsing into a blanket 403.
        $treasurer = User::factory()->create(['status' => 'active']);
        $treasurer->assignRole(Role::firstOrCreate(['name' => 'Treasurer', 'guard_name' => 'web']));
        $treasurer->organizations()->attach($this->orgA->id);

        $response = $this->actingAs($treasurer)
            ->post('/savings-accounts', $this->savingsAccountPayload($this->orgA, $this->memberA));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('savings_accounts', [
            'member_id' => $this->memberA->id,
            'organization_id' => $this->orgA->id,
            'current_balance' => 0,
        ]);
    }

    // ===== Login throttling =====

    public function test_repeated_failed_logins_are_throttled(): void
    {
        $user = User::factory()->create([
            'email' => 'target@financepro.test',
            'password' => bcrypt('correct-horse-battery'),
            'status' => 'active',
        ]);

        // The counter in LoginRequest locks out after 5 failures.
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'target@financepro.test',
                'password' => 'wrong-password',
            ]);
        }

        $this->post('/login', [
            'email' => 'target@financepro.test',
            'password' => 'correct-horse-battery',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    // ===== Member credential strength =====

    /**
     * Every provisioned member account previously received the literal password
     * "password".
     */
    public function test_a_new_member_does_not_receive_a_predictable_default_password(): void
    {
        $created = app(MemberService::class)->create([
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->memberA->branch_id,
            'vicoba_group_id' => VicobaGroup::factory()->create([
                'branch_id' => $this->memberA->branch_id,
            ])->id,
            'gender' => Gender::Male,
            'date_of_birth' => '1990-01-01',
            'joining_date' => now()->toDateString(),
            'member_number' => 'HARDEN-1',
            'first_name' => 'Test',
            'last_name' => 'Member',
            'phone' => '+255700000123',
            'email' => 'hardened@financepro.test',
        ]);

        $temporary = $created['temp_password'];

        $this->assertNotSame('password', $temporary);
        $this->assertGreaterThanOrEqual(12, strlen($temporary));

        $user = User::where('email', 'hardened@financepro.test')->firstOrFail();

        $this->assertTrue(
            Hash::check($temporary, $user->password),
            'The generated password must be the one actually stored.'
        );

        $this->assertFalse(
            Hash::check('password', $user->password),
            'The well-known default password must not authenticate.'
        );
    }

    public function test_the_seeded_super_administrator_does_not_use_a_published_password(): void
    {
        $admin = User::where('email', 'admin@financepro.co.tz')->firstOrFail();

        $this->assertFalse(
            Hash::check('password', $admin->password),
            'A production Super Administrator must not be seeded with a password published in this repository.'
        );
    }

    // ===== Loan repayment row lock =====

    /**
     * The service called lockForUpdate() on a model INSTANCE, which Eloquent
     * forwards to a throwaway builder, so no lock was ever taken and concurrent
     * repayments could both pass validation and both write.
     */
    /**
     * Regression test for a silent concurrency defect.
     *
     * The service used to call lockForUpdate() on a model INSTANCE. Eloquent
     * forwards that call to a throwaway builder which is never executed, so NO
     * locking query was ever emitted and the validate-then-update sequence ran
     * unprotected: two concurrent repayments could both read the same balance
     * and both write.
     *
     * Note on assertion method: the test suite runs on SQLite, whose grammar
     * deliberately strips "FOR UPDATE" from compiled SQL, so the string "for
     * update" can never appear in the log here. What is asserted instead is the
     * observable consequence of the fix - the service must re-read the loan row
     * under the lock before applying money. Under the old code this second read
     * did not happen at all, so the test fails if the fix is reverted.
     */
    /**
     * The old code called lockForUpdate() on a model INSTANCE. Eloquent forwards
     * that to a throwaway builder which is never executed, so no locking query
     * was emitted and the validate-then-update sequence ran unprotected.
     *
     * "FOR UPDATE" cannot be asserted textually here: SQLiteGrammar::compileLock()
     * is a no-op, so the clause is compiled away and never reaches the query log.
     * This test instead asserts the observable consequence of the fix - the loan
     * row is re-read inside the transaction before money is applied.
     */
    public function test_posting_a_repayment_rereads_the_loan_row_before_writing(): void
    {
        $loan = Loan::factory()->active()->create([
            'organization_id' => $this->orgA->id,
            'member_id' => $this->memberA->id,
            'branch_id' => $this->memberA->branch_id,
            'principal_amount' => 100000,
            'disbursed_amount' => 100000,
            'outstanding_balance' => 100000,
            'amount_paid' => 0,
        ]);

        $loanSelects = 0;
        DB::listen(function ($query) use (&$loanSelects) {
            if (stripos($query->sql, 'from "loans"') !== false) {
                $loanSelects++;
            }
        });

        app(LoanRepaymentService::class)->postRepayment(
            $loan,
            1000.0,
            now()->toDateString(),
            'cash',
            null,
            'ref-lock-probe',
            'lock probe',
            'lock-probe-'.uniqid()
        );

        $this->assertGreaterThanOrEqual(
            1,
            $loanSelects,
            'The repayment path must re-read the loan row inside the transaction before writing to it.'
        );

        $this->assertDatabaseHas('loans', [
            'id' => $loan->id,
            'amount_paid' => 1000,
            'outstanding_balance' => 99000,
        ]);
    }
}
