<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDisbursementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'loan_id' => 'required|exists:loans,id',
            'amount' => 'required|numeric|min:0.01|max:999999999.99',
            'disbursement_method' => 'required|string|in:cash,bank_transfer,mobile_money,check',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'loan_id.required' => 'Please select a loan to disburse.',
            'loan_id.exists' => 'The selected loan does not exist.',
            'amount.required' => 'Please enter the disbursement amount.',
            'amount.min' => 'The disbursement amount must be at least 0.01.',
            'disbursement_method.required' => 'Please select a disbursement method.',
            'disbursement_method.in' => 'The selected disbursement method is not valid.',
        ];
    }
}
