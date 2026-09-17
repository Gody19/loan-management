<?php

namespace App\Http\Requests;

use App\Enums\InterestMethod;
use App\Enums\LoanPurpose;
use App\Enums\RepaymentFrequency;
use App\Services\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;

class StoreLoanPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $loanPlanId = $this->route('loan_plan')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:30', 'unique:loan_plans,code,' . ($loanPlanId ?? 'null') . ',id,organization_id,' . $this->organization_id],
            'organization_id' => ['required', 'exists:organizations,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'loan_purpose' => ['required', 'string', 'in:' . implode(',', LoanPurpose::values())],

            // Amount
            'minimum_amount' => ['required', 'numeric', 'min:0'],
            'maximum_amount' => ['required', 'numeric', 'gt:minimum_amount'],

            // Interest
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'interest_method' => ['required', 'string', 'in:' . implode(',', InterestMethod::values())],

            // Term
            'minimum_term' => ['required', 'integer', 'min:1'],
            'maximum_term' => ['required', 'integer', 'gte:minimum_term'],

            // Repayment
            'repayment_frequency' => ['required', 'string', 'in:' . implode(',', RepaymentFrequency::values())],

            // Active loans
            'maximum_active_loans' => ['required', 'integer', 'min:1'],

            // Guarantors
            'requires_guarantor' => ['boolean'],
            'minimum_guarantors' => ['required_if:requires_guarantor,true', 'integer', 'min:0'],

            // Collateral
            'requires_collateral' => ['boolean'],

            // Savings
            'minimum_savings_balance' => ['required', 'numeric', 'min:0'],
            'savings_multiplier' => ['required', 'numeric', 'min:0'],

            // Shares
            'share_multiplier' => ['required', 'numeric', 'min:0'],

            // Loan-to-savings ratio
            'maximum_loan_to_savings_ratio' => ['required', 'numeric', 'min:0'],

            // Grace period
            'grace_period' => ['required', 'integer', 'min:0'],

            // Fees
            'processing_fee' => ['required', 'numeric', 'min:0'],
            'insurance_fee' => ['required', 'numeric', 'min:0'],

            // Late payment
            'late_payment_allowed' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Loan plan name is required.',
            'code.required' => 'Loan plan code is required.',
            'code.unique' => 'This loan plan code already exists for the selected organization.',
            'organization_id.required' => 'Organization is required.',
            'organization_id.exists' => 'Selected organization does not exist.',
            'loan_purpose.required' => 'Loan purpose is required.',
            'loan_purpose.in' => 'Invalid loan purpose.',
            'minimum_amount.required' => 'Minimum amount is required.',
            'minimum_amount.min' => 'Minimum amount cannot be negative.',
            'maximum_amount.required' => 'Maximum amount is required.',
            'maximum_amount.gt' => 'Maximum amount must be greater than minimum amount.',
            'interest_rate.required' => 'Interest rate is required.',
            'interest_rate.min' => 'Interest rate cannot be negative.',
            'interest_rate.max' => 'Interest rate cannot exceed 100%.',
            'interest_method.required' => 'Interest method is required.',
            'interest_method.in' => 'Invalid interest method.',
            'minimum_term.required' => 'Minimum term is required.',
            'minimum_term.min' => 'Minimum term must be at least 1 month.',
            'maximum_term.required' => 'Maximum term is required.',
            'maximum_term.gte' => 'Maximum term must be greater than or equal to minimum term.',
            'repayment_frequency.required' => 'Repayment frequency is required.',
            'repayment_frequency.in' => 'Invalid repayment frequency.',
            'maximum_active_loans.required' => 'Maximum active loans is required.',
            'maximum_active_loans.min' => 'Maximum active loans must be at least 1.',
            'minimum_guarantors.required_if' => 'Minimum guarantors is required when guarantors are required.',
            'minimum_savings_balance.required' => 'Minimum savings balance is required.',
            'minimum_savings_balance.min' => 'Minimum savings balance cannot be negative.',
            'savings_multiplier.required' => 'Savings multiplier is required.',
            'savings_multiplier.min' => 'Savings multiplier cannot be negative.',
            'share_multiplier.required' => 'Share multiplier is required.',
            'share_multiplier.min' => 'Share multiplier cannot be negative.',
            'maximum_loan_to_savings_ratio.required' => 'Maximum loan-to-savings ratio is required.',
            'maximum_loan_to_savings_ratio.min' => 'Maximum loan-to-savings ratio cannot be negative.',
            'grace_period.required' => 'Grace period is required.',
            'grace_period.min' => 'Grace period cannot be negative.',
            'processing_fee.required' => 'Processing fee is required.',
            'processing_fee.min' => 'Processing fee cannot be negative.',
            'insurance_fee.required' => 'Insurance fee is required.',
            'insurance_fee.min' => 'Insurance fee cannot be negative.',
        ];
    }
}
