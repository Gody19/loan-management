<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'organization_id' => ['required', 'exists:organizations,id'],
            'code' => ['required', 'string', 'max:50', 'unique:branches,code'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'manager' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive,suspended'],
        ];

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['code'][array_search('unique:branches,code', $rules['code'])] = 'unique:branches,code,'.$this->route('branch')->id;
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'organization_id.required' => 'Please select an organization.',
            'organization_id.exists' => 'Selected organization does not exist.',
            'code.required' => 'Branch code is required.',
            'code.unique' => 'This branch code is already taken.',
            'name.required' => 'Branch name is required.',
        ];
    }
}
