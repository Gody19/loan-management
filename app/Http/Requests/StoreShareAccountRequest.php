<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShareAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'member_id' => ['required', 'exists:members,id'],
            'share_product_id' => ['required', 'exists:share_products,id'],
            'organization_id' => ['required', 'exists:organizations,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'vicoba_group_id' => ['required', 'exists:vicoba_groups,id'],
        ];
    }
}
