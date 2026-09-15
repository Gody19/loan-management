<?php

namespace App\Services;

use App\Models\SavingsTransaction;
use App\Models\ShareTransaction;
use App\Models\WelfareTransaction;

class ReceiptService
{
    public function __construct(
        private TransactionNumberGenerator $numberGenerator,
    ) {}

    public function generateReceiptNumber(): string
    {
        return $this->numberGenerator->generate('RCT');
    }

    public function generateReceiptData(SavingsTransaction|ShareTransaction|WelfareTransaction $transaction, string $type): array
    {
        $account = $transaction->account;
        $member = $account->member;
        $organization = $account->organization;
        $branch = $account->branch;
        $group = $account->vicobaGroup;

        return [
            'receipt_number' => $this->generateReceiptNumber(),
            'transaction_number' => $transaction->transaction_number,
            'transaction_type' => $type,
            'amount' => $transaction->amount,
            'transaction_date' => $transaction->transaction_date,
            'payment_method' => $transaction->paymentMethod?->name,
            'reference' => $transaction->reference,
            'description' => $transaction->description,
            'member' => [
                'id' => $member->id,
                'member_number' => $member->member_number,
                'full_name' => $member->full_name,
                'phone' => $member->phone,
                'email' => $member->email,
            ],
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'registration_number' => $organization->registration_number,
                'phone' => $organization->phone,
                'email' => $organization->email,
                'address' => $organization->address,
            ],
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
            ],
            'group' => $group ? [
                'id' => $group->id,
                'name' => $group->name,
            ] : null,
            'processed_by' => $transaction->creator?->name ?? $transaction->creator?->fullname,
            'status' => $transaction->status->value,
        ];
    }
}
