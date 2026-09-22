<?php

namespace Database\Seeders;

use App\Enums\MemberStatus;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use App\Models\VicobaGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AddFinanceProMembersSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::where('name', 'FinancePro VICOBA')->first();
        $branch = Branch::where('name', 'Head Office')->where('organization_id', $org->id)->first();
        $group = VicobaGroup::where('name', 'FinancePro Main Group')->first();
        $role = Role::firstOrCreate(['name' => 'VICOBA Member', 'guard_name' => 'web']);

        if (!$org || !$branch || !$group) {
            $this->command->error('FinancePro VICOBA org/branch/group not found. Run TestUserDataSeeder first.');
            return;
        }

        $members = [
            ['first_name' => 'John', 'last_name' => 'Mwalimu', 'phone' => '+255750100001', 'gender' => 'male', 'dob' => '1988-03-12', 'nida' => '301901010001', 'email' => 'john.mwalimu@email.com', 'occupation' => 'Teacher', 'employer' => 'Kinondoni Primary School', 'region' => 'Dar es Salaam', 'district' => 'Kinondoni', 'ward' => 'Mikocheni', 'street' => 'Morogoro Road'],
            ['first_name' => 'Fatuma', 'last_name' => 'Hassan', 'phone' => '+255750100002', 'gender' => 'female', 'dob' => '1992-07-21', 'nida' => '301901010002', 'email' => 'fatuma.hassan@email.com', 'occupation' => 'Trader', 'employer' => 'Self Employed', 'region' => 'Dar es Salaam', 'district' => 'Kinondoni', 'ward' => 'Kijitonyama', 'street' => 'Bagamoyo Road'],
            ['first_name' => 'Emmanuel', 'last_name' => 'Kilonzo', 'phone' => '+255750100003', 'gender' => 'male', 'dob' => '1985-11-05', 'nida' => '301901010003', 'email' => 'emmanuel.kilonzo@email.com', 'occupation' => 'Driver', 'employer' => 'DART Bus', 'region' => 'Dar es Salaam', 'district' => 'Kinondoni', 'ward' => 'Mwananyamala', 'street' => 'Shaaban Robert St'],
            ['first_name' => 'Grace', 'last_name' => 'Mushi', 'phone' => '+255750100004', 'gender' => 'female', 'dob' => '1995-01-18', 'nida' => '301901010004', 'email' => 'grace.mushi@email.com', 'occupation' => 'Nurse', 'employer' => 'Mwananyamala Hospital', 'region' => 'Dar es Salaam', 'district' => 'Kinondoni', 'ward' => 'Mwananyamala', 'street' => 'Keko Street'],
            ['first_name' => 'Ibrahim', 'last_name' => 'Nyerere', 'phone' => '+255750100005', 'gender' => 'male', 'dob' => '1990-06-30', 'nida' => '301901010005', 'email' => 'ibrahim.nyerere@email.com', 'occupation' => 'Accountant', 'employer' => 'Vodacom Tanzania', 'region' => 'Dar es Salaam', 'district' => 'Kinondoni', 'ward' => 'Msasani', 'street' => 'Kernel Road'],
            ['first_name' => 'Neema', 'last_name' => 'Kimaro', 'phone' => '+255750100006', 'gender' => 'female', 'dob' => '1993-09-14', 'nida' => '301901010006', 'email' => 'neema.kimaro@email.com', 'occupation' => 'Tailor', 'employer' => 'Self Employed', 'region' => 'Dar es Salaam', 'district' => 'Kinondoni', 'ward' => 'Magomeni', 'street' => 'United Nations Road'],
            ['first_name' => 'David', 'last_name' => 'Shirima', 'phone' => '+255750100007', 'gender' => 'male', 'dob' => '1987-12-02', 'nida' => '301901010007', 'email' => 'david.shirima@email.com', 'occupation' => 'Mechanic', 'employer' => 'Kariakoo Garage', 'region' => 'Dar es Salaam', 'district' => 'Kinondoni', 'ward' => 'Kijitonyama', 'street' => 'Mwanakwarunde Road'],
            ['first_name' => 'Amina', 'last_name' => 'Juma', 'phone' => '+255750100008', 'gender' => 'female', 'dob' => '1991-04-25', 'nida' => '301901010008', 'email' => 'amina.juma@email.com', 'occupation' => 'Farmer', 'employer' => 'Mbezi Farm', 'region' => 'Dar es Salaam', 'district' => 'Kinondoni', 'ward' => 'Mbezi', 'street' => 'Bagamoyo Road'],
            ['first_name' => 'Peter', 'last_name' => 'Masanja', 'phone' => '+255750100009', 'gender' => 'male', 'dob' => '1982-08-08', 'nida' => '301901010009', 'email' => 'peter.masanja@email.com', 'occupation' => 'Businessman', 'employer' => 'Masanja Enterprises', 'region' => 'Dar es Salaam', 'district' => 'Kinondoni', 'ward' => 'Sinza', 'street' => 'Sinza Palestine St'],
            ['first_name' => 'Happiness', 'last_name' => 'Lugendo', 'phone' => '+255750100010', 'gender' => 'female', 'dob' => '1996-02-11', 'nida' => '301901010010', 'email' => 'happiness.lugendo@email.com', 'occupation' => 'Student', 'employer' => 'University of Dar es Salaam', 'region' => 'Dar es Salaam', 'district' => 'Kinondoni', 'ward' => 'Mikocheni', 'street' => 'Chamazi Street'],
        ];

        $count = 0;
        foreach ($members as $i => $m) {
            $user = User::firstOrCreate(
                ['email' => $m['email']],
                [
                    'fullname' => $m['first_name'] . ' ' . $m['last_name'],
                    'username' => strtolower($m['first_name'] . '.' . $m['last_name']),
                    'phone' => $m['phone'],
                    'nida_number' => $m['nida'],
                    'date_of_birth' => $m['dob'],
                    'gender' => $m['gender'],
                    'status' => 'active',
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]
            );
            $user->syncRoles([$role]);

            Member::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'organization_id' => $org->id,
                    'branch_id' => $branch->id,
                    'vicoba_group_id' => $group->id,
                    'member_number' => 'MBR-FP-' . str_pad($i + 10, 3, '0', STR_PAD_LEFT),
                    'first_name' => $m['first_name'],
                    'last_name' => $m['last_name'],
                    'phone' => $m['phone'],
                    'gender' => $m['gender'],
                    'date_of_birth' => $m['dob'],
                    'national_id' => $m['nida'],
                    'email' => $m['email'],
                    'occupation' => $m['occupation'],
                    'employer_or_business' => $m['employer'],
                    'region' => $m['region'],
                    'district' => $m['district'],
                    'ward' => $m['ward'],
                    'street' => $m['street'],
                    'membership_status' => MemberStatus::Active,
                    'joining_date' => now()->subMonths(3),
                ]
            );
            $count++;
        }

        $this->command->info("Created {$count} members in FinancePro VICOBA / Head Office / FinancePro Main Group.");
    }
}
