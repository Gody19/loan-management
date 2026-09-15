<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSavingsAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'member_id' => ['required', 'exists:members,id'],
            'savings_product_id' => ['required', 'exists:savings_products,id'],
            'organization_id' => ['required', 'exists:organizations,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'vicoba_group_id' => ['required', 'exists:vicoba_groups,id'],
            'opening_date' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'member_id.required' => 'Member is required.',
            'member_id.exists' => 'Selected member does not exist.',
            'savings_product_id.required' => 'Savings product is required.',
            'savings_product_id.exists' => 'Selected savings product does not exist.',
            'organization_id.required' => 'Organization is required.',
            'organization_id.exists' => 'Selected organization does not exist.',
            'branch_id.required' => 'Branch is required.',
            'branch_id.exists' => 'Selected branch does not exist.',
            'vicoba_group_id.required' => 'VICOMBA group is required.',
            'vicoba_group_id.exists' => 'Selected VICOMBA group does not exist.',
            'opening_date.required' => 'Opening date is required.',
            'opening_date.date' => 'Please provide a valid date.',
        ];
    }
}
