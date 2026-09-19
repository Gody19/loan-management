<?php

namespace App\Http\Requests;

use App\Models\Branch;
use App\Models\VicobaGroup;
use Illuminate\Foundation\Http\FormRequest;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'exists:organizations,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'vicoba_group_id' => ['required', 'exists:vicoba_groups,id'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', 'in:male,female,other,prefer_not_to_say'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'phone' => ['required', 'string', 'max:20'],
            'alternate_phone' => ['nullable', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'national_id' => ['nullable', 'string', 'max:50', 'unique:members,national_id'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'employer_or_business' => ['nullable', 'string', 'max:255'],
            'marital_status' => ['nullable', 'in:single,married,divorced,widowed,separated,other'],
            'address' => ['nullable', 'string', 'max:500'],
            'region' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'ward' => ['nullable', 'string', 'max:100'],
            'street' => ['nullable', 'string', 'max:100'],
            'joining_date' => ['required', 'date'],
            'membership_status' => ['nullable', 'in:pending,active,suspended,inactive,exited,blacklisted'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->any()) {
                return;
            }

            $branchId = $this->input('branch_id');
            $groupId = $this->input('vicoba_group_id');
            $orgId = $this->input('organization_id');

            $branch = Branch::find($branchId);
            if ($branch && $branch->organization_id != $orgId) {
                $validator->errors()->add('branch_id', 'The selected branch does not belong to the selected organization.');
            }

            $group = VicobaGroup::find($groupId);
            if ($group && $group->branch_id != $branchId) {
                $validator->errors()->add('vicoba_group_id', 'The selected group does not belong to the selected branch.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'organization_id.required' => 'Please select an organization.',
            'organization_id.exists' => 'The selected organization is invalid.',
            'branch_id.required' => 'Please select a branch.',
            'branch_id.exists' => 'The selected branch is invalid.',
            'vicoba_group_id.required' => 'Please select a VICOBA group.',
            'vicoba_group_id.exists' => 'The selected VICOBA group is invalid.',
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'gender.required' => 'Please select a gender.',
            'phone.required' => 'Phone number is required.',
            'joining_date.required' => 'Joining date is required.',
            'joining_date.date' => 'Please provide a valid date.',
            'national_id.unique' => 'A member with this National ID already exists.',
            'email.email' => 'Please provide a valid email address.',
            'email.required' => 'Email is required for portal access.',
            'email.unique' => 'A user with this email already exists.',
        ];
    }
}
