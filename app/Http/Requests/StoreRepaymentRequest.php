<?php

namespace App\Http\Requests;

use App\Enums\RepaymentFrequency;
use Illuminate\Foundation\Http\FormRequest;

class StoreRepaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0.01|max:999999999.99',
            'payment_date' => 'required|date|before_or_equal:today',
            'payment_method' => 'required|string|in:cash,mobile_money,bank_transfer,check',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'idempotency_key' => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Please enter the payment amount.',
            'amount.min' => 'Payment amount must be at least TSh 0.01.',
            'payment_date.required' => 'Please select a payment date.',
            'payment_date.before_or_equal' => 'Payment date cannot be in the future.',
            'payment_method.required' => 'Please select a payment method.',
        ];
    }
}
