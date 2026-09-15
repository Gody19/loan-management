<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVicobaGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'branch_id' => ['required', 'exists:branches,id'],
            'code' => ['required', 'string', 'max:50', 'unique:vicoba_groups,code'],
            'name' => ['required', 'string', 'max:255'],
            'meeting_day' => ['nullable', 'string', 'max:20'],
            'meeting_time' => ['nullable', 'date_format:H:i'],
            'meeting_location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'in:active,inactive,suspended'],
        ];

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['code'][array_search('unique:vicoba_groups,code', $rules['code'])] = 'unique:vicoba_groups,code,' . $this->route('vicoba_group')->id;
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'branch_id.required' => 'Please select a branch.',
            'branch_id.exists' => 'Selected branch does not exist.',
            'code.required' => 'Group code is required.',
            'code.unique' => 'This group code is already taken.',
            'name.required' => 'Group name is required.',
        ];
    }
}
