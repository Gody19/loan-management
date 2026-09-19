<?php

namespace App\Http\Requests;

use App\Enums\LoanPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemberLoanApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->member !== null
            && $this->user()->member->membership_status->value === 'active';
    }

    public function rules(): array
    {
        $plan = $this->route('loanPlan');

        return [
            'requested_amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:999999999999',
                Rule::when($plan, [
                    'min:' . ($plan?->minimum_amount ?? 0),
                    'max:' . ($plan?->maximum_amount ?? PHP_FLOAT_MAX),
                ]),
            ],
            'requested_term' => [
                'required',
                'integer',
                'min:1',
                'max:120',
                Rule::when($plan, [
                    'min:' . ($plan?->minimum_term ?? 1),
                    'max:' . ($plan?->maximum_term ?? 120),
                ]),
            ],
            'loan_purpose' => [
                'required',
                Rule::enum(LoanPurpose::class),
            ],
            'purpose_description' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'requested_amount.required' => 'Please enter the loan amount you need.',
            'requested_amount.numeric' => 'Loan amount must be a valid number.',
            'requested_amount.min' => 'The requested amount is below the minimum for this plan.',
            'requested_amount.max' => 'The requested amount exceeds the maximum for this plan.',
            'requested_term.required' => 'Please specify the loan term in months.',
            'requested_term.integer' => 'Loan term must be a whole number of months.',
            'requested_term.min' => 'The requested term is below the minimum for this plan.',
            'requested_term.max' => 'The requested term exceeds the maximum for this plan.',
            'loan_purpose.required' => 'Please select the purpose of this loan.',
            'loan_purpose.enum' => 'The selected loan purpose is not valid.',
        ];
    }
}
