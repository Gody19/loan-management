<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSavingsProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:savings_products,code'],
            'organization_id' => ['required', 'exists:organizations,id'],
            'minimum_amount' => ['nullable', 'numeric'],
            'maximum_amount' => ['nullable', 'numeric'],
            'minimum_balance' => ['nullable', 'numeric'],
            'allow_withdrawal' => ['boolean'],
            'withdrawal_limit' => ['nullable', 'numeric'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Product name is required.',
            'code.required' => 'Product code is required.',
            'code.unique' => 'This product code already exists.',
            'organization_id.required' => 'Organization is required.',
            'organization_id.exists' => 'Selected organization does not exist.',
            'minimum_amount.numeric' => 'Minimum amount must be a valid number.',
            'maximum_amount.numeric' => 'Maximum amount must be a valid number.',
            'minimum_balance.numeric' => 'Minimum balance must be a valid number.',
            'withdrawal_limit.numeric' => 'Withdrawal limit must be a valid number.',
        ];
    }
}
