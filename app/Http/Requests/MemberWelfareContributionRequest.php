<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MemberWelfareContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'welfare_account_id' => ['required', 'integer', 'exists:welfare_accounts,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'transaction_date' => ['required', 'date'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'welfare_account_id.required' => 'Please select a welfare account.',
            'welfare_account_id.exists' => 'The selected welfare account is invalid.',
            'amount.required' => 'Please enter an amount.',
            'amount.numeric' => 'The amount must be a valid number.',
            'amount.min' => 'The amount must be at least 0.01.',
            'payment_method_id.required' => 'Please select a payment method.',
            'payment_method_id.exists' => 'The selected payment method is invalid.',
            'transaction_date.required' => 'Please select a transaction date.',
            'transaction_date.date' => 'Please enter a valid date.',
        ];
    }
}
