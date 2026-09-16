<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGuarantorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guarantor_member_id' => ['required', 'exists:members,id'],
            'guaranteed_amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'guarantor_member_id.required' => 'Please select a guarantor member.',
            'guarantor_member_id.exists' => 'Selected guarantor member does not exist.',
            'guaranteed_amount.required' => 'Guaranteed amount is required.',
            'guaranteed_amount.min' => 'Guaranteed amount must be at least 0.01.',
        ];
    }
}
