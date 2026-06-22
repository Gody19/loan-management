<?php

namespace App\Http\Controllers;

use App\Models\Repayment;
use App\Services\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function pay(Request $request, Repayment $repayment)
    {
        $request->validate([
            'phone' => 'required|string|regex:/^255\d{9}$/',
        ]);

        if ($repayment->status === 'paid') {
            return back()->with('error', 'This installment has already been paid.');
        }

        if ($repayment->loan->user_id !== auth()->id()) {
            abort(403);
        }

        $orderId = 'REP-'.$repayment->id.'-'.now()->timestamp;
        $remaining = max($repayment->amount - $repayment->paid_amount, 0);

        $webhookUrl = config('payments.mongike.webhook_url') ?? route('payments.webhook');

        $gateway = new PaymentGateway;
        $result = $gateway->initiatePayment($orderId, $remaining, $request->phone, $webhookUrl);

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        session()->flash('payment_order_id', $result['data']['order_id'] ?? $orderId);

        return back()->with('success', 'Payment initiated. Check your phone to enter PIN and complete payment.');
    }

    public function webhook(Request $request)
    {
        $apiKey = config('payments.mongike.api_key');

        $incomingKey = $request->header('x-api-key');
        if (! $incomingKey || $incomingKey !== $apiKey) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $orderId = $request->input('order_id');
        $paymentStatus = $request->input('payment_status');

        if (! $orderId || $paymentStatus !== 'COMPLETED') {
            return response()->json(['status' => 'ignored']);
        }

        if (! preg_match('/^REP-(\d+)-/', $orderId, $matches)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid order_id format'], 400);
        }

        $repaymentId = (int) $matches[1];

        DB::transaction(function () use ($repaymentId, $request, $orderId) {
            $repayment = Repayment::lockForUpdate()->find($repaymentId);

            if (! $repayment || $repayment->status === 'paid') {
                return;
            }

            $amount = $repayment->amount;
            $paidAmount = $repayment->paid_amount;
            $remaining = $amount - $paidAmount;

            $repayment->update([
                'paid_amount' => $amount,
                'status' => 'paid',
                'paid_date' => now(),
                'reference_number' => $request->input('reference') ?? $orderId,
            ]);

            $loan = $repayment->loan;
            $loan->increment('amount_paid', $remaining);

            $allPaid = $loan->repayments()->where('status', '!=', 'paid')->count() === 0;
            if ($allPaid) {
                $loan->update(['status' => 'completed']);
            }
        });

        return response()->json(['status' => 'ok']);
    }
}
