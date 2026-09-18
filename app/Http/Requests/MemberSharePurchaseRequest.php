<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MemberSharePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'share_account_id' => ['required', 'integer', 'exists:share_accounts,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'transaction_date' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'share_account_id.required' => 'Please select a share account.',
            'quantity.required' => 'Please enter the number of shares.',
            'quantity.integer' => 'The number of shares must be a whole number.',
            'quantity.min' => 'You must purchase at least 1 share.',
            'payment_method_id.required' => 'Please select a payment method.',
            'transaction_date.required' => 'Please select a transaction date.',
        ];
    }
}
