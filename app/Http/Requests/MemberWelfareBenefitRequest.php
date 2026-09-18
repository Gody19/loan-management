<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MemberWelfareBenefitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'welfare_account_id' => ['required', 'integer', 'exists:welfare_accounts,id'],
            'requested_amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:100', 'unique:welfare_benefit_requests,idempotency_key'],
        ];
    }

    public function messages(): array
    {
        return [
            'welfare_account_id.required' => 'Please select a welfare account.',
            'welfare_account_id.exists' => 'The selected welfare account is invalid.',
            'requested_amount.required' => 'Please enter the benefit amount.',
            'requested_amount.numeric' => 'The amount must be a valid number.',
            'requested_amount.min' => 'The amount must be at least 0.01.',
            'idempotency_key.unique' => 'This benefit request has already been submitted.',
        ];
    }
}
