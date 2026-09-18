<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MemberSavingsWithdrawRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'savings_account_id' => ['required', 'integer', 'exists:savings_accounts,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'transaction_date' => ['required', 'date'],
            'idempotency_key' => ['nullable', 'string', 'max:100', 'unique:savings_transactions,idempotency_key'],
        ];
    }

    public function messages(): array
    {
        return [
            'savings_account_id.required' => 'Please select a savings account.',
            'amount.required' => 'Please enter an amount.',
            'amount.numeric' => 'The amount must be a valid number.',
            'amount.min' => 'The amount must be at least 0.01.',
            'payment_method_id.required' => 'Please select a payment method.',
            'transaction_date.required' => 'Please select a transaction date.',
            'idempotency_key.unique' => 'This transaction has already been submitted.',
        ];
    }
}
