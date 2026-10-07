<?php

namespace Database\Seeders;

use App\Enums\DocumentType;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\MemberStatus;
use App\Enums\Relationship;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Models\Branch;
use App\Models\Member;
use App\Models\MemberDocument;
use App\Models\MemberNextOfKin;
use App\Models\MemberStatusHistory;
use App\Models\User;
use App\Models\VicobaGroup;
use Database\Seeders\Concerns\FillsTables;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class PeopleSeeder extends Seeder
{
    use FillsTables;

    public function run(): void
    {
        $this->users();
        $this->roles();
        $this->members();
        $this->nextOfKin();
        $this->documents();
        $this->statusHistory();

        $this->report('People ready.');
    }

    private function users(): void
    {
        $password = Hash::make('password');
        $genders = ['male', 'female', 'other'];
        $branches = Branch::where('organization_id', self::ORG_ID)->orderBy('id')->pluck('id')->all();

        if (! $branches) {
            return;
        }

        while ($this->gap('users') > 0) {
            $user = User::create([
                'fullname' => fake()->name(),
                'username' => Str::slug(fake()->unique()->userName(), '.').fake()->numberBetween(10, 99),
                'email' => fake()->unique()->safeEmail(),
                'phone' => '+2557'.fake()->numerify('########'),
                'nida_number' => fake()->unique()->numerify('199###########'),
                'status' => UserStatus::Active,
                'date_of_birth' => fake()->dateTimeBetween('-62 years', '-23 years')->format('Y-m-d'),
                'gender' => $this->pick($genders),
                'is_active' => 1,
                'password' => $password,
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();

            if (! DB::table('organization_user')->where('organization_id', self::ORG_ID)->where('user_id', $user->id)->exists()) {
                DB::table('organization_user')->insert([
                    'organization_id' => self::ORG_ID,
                    'user_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (! DB::table('branch_user')->where('user_id', $user->id)->exists()) {
                DB::table('branch_user')->insert([
                    'branch_id' => $this->pick($branches),
                    'user_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->bump('users');
        }
    }

    private function roles(): void
    {
        $names = [
            'Organization Administrator',
            'Branch Manager',
            'Loan Officer',
            'Credit Officer',
            'Treasurer',
            'Accountant',
            'Secretary',
            'Collection Officer',
            'Auditor',
        ];

        $userIds = DB::table('organization_user')->where('organization_id', self::ORG_ID)->orderBy('user_id')->pluck('user_id')->all();

        foreach ($userIds as $index => $userId) {
            $role = Role::where('name', $names[$index % count($names)])->first();
            $user = User::find($userId);

            if (! $role || ! $user) {
                continue;
            }

            try {
                $user->assignRole($role);
            } catch (\Throwable) {
                continue;
            }
        }
    }

    private function members(): void
    {
        $occupations = ['Farmer', 'Tailor', 'Teacher', 'Driver', 'Businesswoman', 'Carpenter', 'Nurse', 'Shopkeeper', 'Mechanic', 'Hairdresser', 'Consultant', 'Baker'];
        $regions = ['Dar es Salaam', 'Arusha', 'Mwanza', 'Dodoma', 'Mbeya', 'Tanga', 'Morogoro', 'Zanzibar', 'Iringa', 'Tabora'];
        $districts = ['Kinondoni', 'Temeke', 'Ilala', 'Meru', 'Nyamagana', 'Ubungo', 'Kaskazini', 'Central'];
        $wards = ['Miburani', 'Kambwichi', 'Kijitonyama', 'Mbezi', 'Kimwanyi', 'Sono', 'Chalinze', 'Vwawa'];
        $marital = [MaritalStatus::Married, MaritalStatus::Single, MaritalStatus::Widowed, MaritalStatus::Divorced];
        $genders = [Gender::Male, Gender::Female];
        $statuses = [
            MemberStatus::Active, MemberStatus::Active, MemberStatus::Active, MemberStatus::Active, MemberStatus::Active,
            MemberStatus::Active, MemberStatus::Pending, MemberStatus::Suspended, MemberStatus::Inactive,
        ];

        $branches = Branch::where('organization_id', self::ORG_ID)->orderBy('id')->pluck('id')->all();
        $groups = VicobaGroup::whereIn('branch_id', $branches)->orderBy('id')->get();
        $groupsByBranch = [];

        foreach ($groups as $group) {
            $groupsByBranch[$group->branch_id][] = $group;
        }

        if (! $groupsByBranch) {
            return;
        }

        $sequence = Member::count();

        while ($this->gap('members') > 0) {
            $sequence++;
            $branchId = $branches[$sequence % count($branches)];
            $options = $groupsByBranch[$branchId] ?? [];
            $groupId = $options ? $options[$sequence % count($options)]->id : null;

            if (! $groupId) {
                continue;
            }

            Member::create([
                'organization_id' => self::ORG_ID,
                'branch_id' => $branchId,
                'vicoba_group_id' => $groupId,
                'user_id' => null,
                'member_number' => sprintf('MBR-FP-%04d', $sequence),
                'first_name' => fake()->firstName(),
                'middle_name' => fake()->boolean(35) ? fake()->firstName() : null,
                'last_name' => fake()->lastName(),
                'gender' => $this->pick($genders),
                'date_of_birth' => fake()->dateTimeBetween('-60 years', '-21 years')->format('Y-m-d'),
                'phone' => '+2557'.fake()->unique()->numerify('########'),
                'alternate_phone' => fake()->boolean(25) ? '+2557'.fake()->numerify('########') : null,
                'email' => fake()->unique()->safeEmail(),
                'national_id' => fake()->unique()->numerify('1#############'),
                'occupation' => $this->pick($occupations),
                'employer_or_business' => fake()->company(),
                'marital_status' => $this->pick($marital),
                'address' => fake()->streetAddress(),
                'region' => $this->pick($regions),
                'district' => $this->pick($districts),
                'ward' => $this->pick($wards),
                'street' => fake()->streetName(),
                'joining_date' => $this->daysAgo(1200, 20)->toDateString(),
                'membership_status' => $this->pick($statuses),
                'profile_photo' => null,
                'notes' => null,
                'created_by' => self::ACTOR_ID,
            ]);

            $this->bump('members');
        }
    }

    private function nextOfKin(): void
    {
        $relationships = [Relationship::Spouse, Relationship::Parent, Relationship::Sibling, Relationship::Child, Relationship::Guardian, Relationship::Relative];
        $members = Member::where('organization_id', self::ORG_ID)->orderBy('id')->get();

        foreach ($members as $index => $member) {
            if ($this->gap('member_next_of_kins') <= 0) {
                break;
            }

            if (MemberNextOfKin::where('member_id', $member->id)->exists()) {
                continue;
            }

            MemberNextOfKin::create([
                'member_id' => $member->id,
                'full_name' => fake()->name(),
                'relationship' => $relationships[$index % count($relationships)],
                'phone' => '+2557'.fake()->numerify('########'),
                'alternate_phone' => fake()->boolean(20) ? '+2557'.fake()->numerify('########') : null,
                'address' => fake()->streetAddress(),
                'is_primary' => 1,
                'notes' => null,
            ]);

            $this->bump('member_next_of_kins');
        }
    }

    private function documents(): void
    {
        $types = DocumentType::cases();
        $verifications = [VerificationStatus::Verified, VerificationStatus::Verified, VerificationStatus::Pending, VerificationStatus::Rejected];
        $members = Member::where('organization_id', self::ORG_ID)->orderBy('id')->get();

        foreach ($members as $index => $member) {
            if ($this->gap('member_documents', 30) <= 0) {
                break;
            }

            if ($index % 2 === 1) {
                continue;
            }

            $type = $types[$index % count($types)];
            $verification = $verifications[$index % count($verifications)];
            $verified = $verification === VerificationStatus::Verified;

            MemberDocument::create([
                'member_id' => $member->id,
                'document_type' => $type,
                'document_number' => fake()->unique()->numerify('DOC-########'),
                'file_path' => 'documents/members/'.$member->member_number.'/'.fake()->uuid().'.pdf',
                'original_filename' => strtolower($member->member_number.'-'.$type->value.'.pdf'),
                'mime_type' => 'application/pdf',
                'file_size' => fake()->numberBetween(60000, 4000000),
                'verification_status' => $verification,
                'verified_by' => $verified ? self::ACTOR_ID : null,
                'verified_at' => $verified ? $this->daysAgo(120) : null,
                'notes' => $verification === VerificationStatus::Rejected ? 'Scanned copy is not legible, please resubmit.' : null,
            ]);

            $this->bump('member_documents');
        }
    }

    private function statusHistory(): void
    {
        $members = Member::where('organization_id', self::ORG_ID)->orderBy('id')->get();

        foreach ($members as $index => $member) {
            if ($this->gap('member_status_histories', 30) <= 0) {
                break;
            }

            if ($index % 3 !== 0) {
                continue;
            }

            MemberStatusHistory::create([
                'member_id' => $member->id,
                'old_status' => MemberStatus::Pending,
                'new_status' => $member->membership_status,
                'reason' => 'Registration documents verified and member onboarded.',
                'changed_by' => self::ACTOR_ID,
                'changed_at' => $member->created_at ?? now(),
            ]);

            $this->bump('member_status_histories');
        }
    }
}
