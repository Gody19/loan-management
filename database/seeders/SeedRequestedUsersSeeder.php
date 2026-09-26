<?php

namespace Database\Seeders;

use App\Enums\MemberStatus;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use App\Models\VicobaGroup;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Restore the requested top-level users (as shown in the users table) with the
 * shared password "password". Idempotent and additive only: it never migrates
 * or wipes other tables. Runs the RolePermissionSeeder first so roles exist.
 */
class SeedRequestedUsersSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $superAdminRole = Role::firstOrCreate(['name' => 'Super Administrator', 'guard_name' => 'web']);
        $orgAdminRole = Role::firstOrCreate(['name' => 'Organization Administrator', 'guard_name' => 'web']);
        $memberRole = Role::firstOrCreate(['name' => 'VICOBA Member', 'guard_name' => 'web']);

        $org = Organization::firstOrCreate(
            ['registration_number' => 'ORG-FP-001'],
            [
                'name' => 'FinancePro VICOBA',
                'phone' => '+255712345678',
                'email' => 'info@financepro.co.tz',
                'region' => 'Dar es Salaam',
                'district' => 'Kinondoni',
                'status' => 'active',
            ]
        );

        $branch = Branch::firstOrCreate(
            ['code' => 'BR-FP-001'],
            [
                'organization_id' => $org->id,
                'name' => 'Head Office',
                'status' => 'active',
            ]
        );

        $group = VicobaGroup::firstOrCreate(
            ['name' => 'FinancePro Main Group'],
            [
                'branch_id' => $branch->id,
                'code' => 'GRP-FP-001',
                'status' => 'active',
            ]
        );

        $users = [
            [
                'id' => 1,
                'fullname' => 'System Administrator',
                'username' => 'admin',
                'email' => 'admin@financepro.co.tz',
                'phone' => '+255700000000',
                'nida_number' => '00000000000000000000',
                'date_of_birth' => null,
                'gender' => null,
                'created_at' => '2026-09-21 19:50:28',
                'updated_at' => '2026-09-21 19:50:28',
                'role' => $superAdminRole,
            ],
            [
                'id' => 2,
                'fullname' => 'Test User',
                'username' => 'testuser',
                'email' => 'test@financepro.co.tz',
                'phone' => '+255711111111',
                'nida_number' => '11111111111111111111',
                'date_of_birth' => null,
                'gender' => null,
                'created_at' => '2026-09-21 19:50:29',
                'updated_at' => '2026-09-21 19:50:29',
                'role' => $superAdminRole,
            ],
            [
                'id' => 4,
                'fullname' => 'Gody Ouwa',
                'username' => 'gody12919',
                'email' => 'gody12919@gmail.com',
                'phone' => '+255712345679',
                'nida_number' => '201901010001',
                'date_of_birth' => null,
                'gender' => null,
                'created_at' => '2026-09-21 19:58:43',
                'updated_at' => '2026-09-21 19:58:43',
                'role' => $orgAdminRole,
                'attach_org' => true,
            ],
            [
                'id' => 5,
                'fullname' => 'Irene Deusi',
                'username' => 'irenedeusi',
                'email' => 'irenedeusi55@gmail.com',
                'phone' => '+255734567890',
                'nida_number' => '201901010002',
                'date_of_birth' => null,
                'gender' => null,
                'created_at' => '2026-09-21 19:58:43',
                'updated_at' => '2026-09-21 19:58:43',
                'role' => $memberRole,
                'attach_org' => true,
                'member_record' => true,
            ],
        ];

        $seeded = [];
        foreach ($users as $row) {
            $role = $row['role'];
            $attachOrg = $row['attach_org'] ?? false;
            $memberRecord = $row['member_record'] ?? false;
            unset($row['role'], $row['attach_org'], $row['member_record']);

            $fill = $row;
            $email = $row['email'];
            unset($fill['id']);

            $user = User::firstOrNew(['email' => $email]);
            if (! $user->exists) {
                $fill['id'] = $row['id'];
            }
            $fill['status'] = 'active';
            $fill['is_active'] = true;
            $fill['password'] = 'password';
            $fill['email_verified_at'] = null;

            $user->forceFill($fill)->save();
            $user->syncRoles($role->name);

            if ($attachOrg) {
                $user->organizations()->syncWithoutDetaching([$org->id]);
                if ($role->name === 'Organization Administrator') {
                    $user->branches()->syncWithoutDetaching([$branch->id]);
                }
            }

            if ($memberRecord) {
                Member::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'organization_id' => $org->id,
                        'branch_id' => $branch->id,
                        'vicoba_group_id' => $group->id,
                        'member_number' => 'MBR-FP-001',
                        'first_name' => 'Irene',
                        'last_name' => 'Deusi',
                        'phone' => '+255734567890',
                        'gender' => 'female',
                        'date_of_birth' => '1990-05-15',
                        'national_id' => '201901010002',
                        'email' => 'irenedeusi55@gmail.com',
                        'membership_status' => MemberStatus::Active,
                        'joining_date' => now()->subYear(),
                    ]
                );
            }

            $seeded[] = "{$user->fullname} <{$email}> ({$role->name})";
        }

        $this->command->info('Requested users seeded. Password for all = "password".');
        foreach ($seeded as $line) {
            $this->command->info('  '.$line);
        }
    }
}