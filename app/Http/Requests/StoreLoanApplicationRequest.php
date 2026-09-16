<?php

namespace App\Http\Requests;

use App\Enums\LoanPurpose;
use App\Enums\RepaymentFrequency;
use Illuminate\Foundation\Http\FormRequest;

class StoreLoanApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'member_id' => ['required', 'exists:members,id'],
            'loan_plan_id' => ['required', 'exists:loan_plans,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'vicoba_group_id' => ['nullable', 'exists:vicoba_groups,id'],
            'requested_amount' => ['required', 'numeric', 'min:1'],
            'requested_term' => ['required', 'integer', 'min:1'],
            'repayment_frequency' => ['required', 'string', 'in:' . implode(',', RepaymentFrequency::values())],
            'loan_purpose' => ['required', 'string', 'in:' . implode(',', LoanPurpose::values())],
            'purpose_description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'member_id.required' => 'Please select a member.',
            'member_id.exists' => 'Selected member does not exist.',
            'loan_plan_id.required' => 'Please select a loan plan.',
            'loan_plan_id.exists' => 'Selected loan plan does not exist.',
            'branch_id.required' => 'Please select a branch.',
            'branch_id.exists' => 'Selected branch does not exist.',
            'requested_amount.required' => 'Requested amount is required.',
            'requested_amount.min' => 'Requested amount must be at least 1.',
            'requested_term.required' => 'Requested term is required.',
            'requested_term.min' => 'Term must be at least 1 month.',
            'repayment_frequency.required' => 'Repayment frequency is required.',
            'loan_purpose.required' => 'Loan purpose is required.',
        ];
    }
}
