<?php

namespace App\Services;

use App\Enums\WelfareBenefitRequestStatus;
use App\Models\PaymentMethod;
use App\Models\WelfareAccount;
use App\Models\WelfareBenefitRequest;
use Illuminate\Support\Facades\DB;

class WelfareBenefitRequestService
{
    public function __construct(
        private TransactionNumberGenerator $numberGenerator,
        private AuditService $auditService,
        private WelfareTransactionService $welfareService,
    ) {}

    public function createRequest(WelfareAccount $account, array $data): WelfareBenefitRequest
    {
        $amount = (float) $data['requested_amount'];

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Benefit amount must be greater than zero.');
        }

        if ($account->status->value !== 'active') {
            throw new \InvalidArgumentException('Welfare account is not active.');
        }

        if (isset($data['idempotency_key']) && WelfareBenefitRequest::where('idempotency_key', $data['idempotency_key'])->exists()) {
            throw new \InvalidArgumentException('This benefit request has already been submitted.');
        }

        return DB::transaction(function () use ($account, $data, $amount) {
            $request = WelfareBenefitRequest::create([
                'welfare_account_id' => $account->id,
                'member_id' => $account->member_id,
                'organization_id' => $account->organization_id,
                'branch_id' => $account->branch_id,
                'vicoba_group_id' => $account->vicoba_group_id,
                'request_number' => $this->numberGenerator->generate('WBR'),
                'requested_amount' => $amount,
                'reason' => $data['reason'] ?? null,
                'status' => WelfareBenefitRequestStatus::Pending,
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            $this->auditService->log('welfare.benefit_request_created', $request, [], [
                'request_number' => $request->request_number,
                'account_number' => $account->account_number,
                'amount' => $amount,
            ]);

            return $request;
        });
    }

    public function approveRequest(WelfareBenefitRequest $request, array $data): WelfareBenefitRequest
    {
        if ($request->status !== WelfareBenefitRequestStatus::Pending) {
            throw new \InvalidArgumentException('Only pending benefit requests can be approved.');
        }

        if (! isset($data['payment_method_id'])) {
            throw new \InvalidArgumentException('Payment method is required for approval.');
        }

        $paymentMethod = PaymentMethod::find($data['payment_method_id']);
        if (! $paymentMethod || $paymentMethod->status !== 'active') {
            throw new \InvalidArgumentException('Selected payment method is not active.');
        }
        if ($paymentMethod->organization_id !== $request->organization_id) {
            throw new \InvalidArgumentException('Selected payment method does not belong to this organization.');
        }

        return DB::transaction(function () use ($request, $data, $paymentMethod) {
            $account = WelfareAccount::withoutGlobalScopes()
                ->where('id', $request->welfare_account_id)
                ->lockForUpdate()
                ->first();

            $balanceBefore = (float) $account->current_balance;
            $amount = (float) $request->requested_amount;

            if ($balanceBefore < $amount) {
                throw new \InvalidArgumentException('Insufficient welfare balance for this benefit.');
            }

            $transaction = $this->welfareService->benefit($account, [
                'amount' => $amount,
                'payment_method_id' => $paymentMethod->id,
                'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
                'reference' => $request->request_number,
                'description' => $request->reason ?? "Benefit approved from request {$request->request_number}",
            ]);

            $request->update([
                'status' => WelfareBenefitRequestStatus::Approved,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'payment_method_id' => $paymentMethod->id,
                'welfare_transaction_id' => $transaction->id,
            ]);

            $this->auditService->log('welfare.benefit_request_approved', $request, [
                'status' => WelfareBenefitRequestStatus::Pending->value,
            ], [
                'status' => WelfareBenefitRequestStatus::Approved->value,
                'transaction_number' => $transaction->transaction_number,
                'amount' => $amount,
            ]);

            return $request;
        });
    }

    public function rejectRequest(WelfareBenefitRequest $request, string $reason): WelfareBenefitRequest
    {
        if ($request->status !== WelfareBenefitRequestStatus::Pending) {
            throw new \InvalidArgumentException('Only pending benefit requests can be rejected.');
        }

        return DB::transaction(function () use ($request, $reason) {
            $request->update([
                'status' => WelfareBenefitRequestStatus::Rejected,
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $this->auditService->log('welfare.benefit_request_rejected', $request, [
                'status' => WelfareBenefitRequestStatus::Pending->value,
            ], [
                'status' => WelfareBenefitRequestStatus::Rejected->value,
                'rejection_reason' => $reason,
            ]);

            return $request;
        });
    }
}
