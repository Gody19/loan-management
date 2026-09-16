<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWelfareFundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'organization_id' => ['required', 'exists:organizations,id'],
            'contribution_type' => ['required', 'in:fixed,variable'],
            'default_amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Welfare fund name is required.',
            'code.required' => 'Welfare fund code is required.',
            'organization_id.required' => 'Organization is required.',
            'organization_id.exists' => 'Selected organization does not exist.',
            'contribution_type.required' => 'Contribution type is required.',
            'contribution_type.in' => 'Contribution type must be either fixed or variable.',
            'default_amount.required' => 'Default amount is required.',
            'default_amount.numeric' => 'Default amount must be a valid number.',
            'default_amount.min' => 'Default amount must be at least 0.01.',
        ];
    }
}
