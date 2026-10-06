<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Savings Receipt - {{ $transaction->transaction_number ?? 'N/A' }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 13px; color: #333; background: #f5f5f5; }
        .receipt { max-width: 800px; margin: 20px auto; background: #fff; border: 1px solid #ddd; padding: 30px; }
        @media print {
            body { background: #fff; }
            .receipt { border: none; box-shadow: none; margin: 0; padding: 20px; }
            .no-print { display: none !important; }
        }
        .receipt-header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 20px; }
        .receipt-header h1 { font-size: 22px; font-weight: 700; margin-bottom: 5px; }
        .receipt-header .org-name { font-size: 15px; color: #555; margin-bottom: 3px; }
        .receipt-header .receipt-title { font-size: 18px; font-weight: 600; text-transform: uppercase; margin-top: 10px; color: #2563eb; }
        .receipt-info { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .receipt-info div { flex: 1; }
        .receipt-info .label { font-weight: 600; color: #555; font-size: 11px; text-transform: uppercase; }
        .receipt-info .value { font-size: 14px; margin-top: 2px; }
        .receipt-details { margin-bottom: 20px; }
        .receipt-details table { width: 100%; border-collapse: collapse; }
        .receipt-details table td { padding: 8px 10px; border-bottom: 1px solid #eee; }
        .receipt-details table td:first-child { font-weight: 600; color: #555; width: 45%; }
        .receipt-amount { text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px; margin: 20px 0; }
        .receipt-amount .amount-label { font-size: 12px; text-transform: uppercase; color: #666; }
        .receipt-amount .amount-value { font-size: 32px; font-weight: 700; color: #16a34a; margin-top: 5px; }
        .receipt-footer { border-top: 2px solid #333; padding-top: 15px; margin-top: 20px; text-align: center; }
        .receipt-footer p { font-size: 11px; color: #666; margin-bottom: 3px; }
        .receipt-footer .thank-you { font-size: 14px; font-weight: 600; color: #333; margin-top: 10px; }
        .signature-area { display: flex; justify-content: space-between; margin-top: 40px; padding-top: 10px; }
        .signature-area .sig-block { text-align: center; width: 45%; }
        .signature-area .sig-line { border-top: 1px solid #333; padding-top: 5px; font-size: 11px; color: #555; }
        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .status-completed { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef9c3; color: #854d0e; }
        .print-btn { position: fixed; bottom: 20px; right: 20px; z-index: 100; }
        .receipt-brand { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 12px; }
        .receipt-brand .print-brand-text { text-align: left; }
    </style>

    {{-- FinancePro shared print kit: brand mark, watermark, footer --}}
    @include('layouts.print.styles', ['inApp' => false])
</head>
<body>

    <button class="btn btn-primary btn-lg no-print print-btn" onclick="window.print()">
        <i class="bi bi-printer"></i> Print Receipt
    </button>

    @include('layouts.print.watermark')

    <div class="receipt">
        <div class="receipt-header">
            <div class="receipt-brand print-brand">
                <img class="print-brand-mark" src="{{ asset('images/financepro-mark.svg') }}" alt="">
                <div class="print-brand-text">
                    <strong>{{ config('app.name', 'FinancePro') }}</strong>
                    <span>VICOBA System</span>
                </div>
            </div>
            <div class="org-name">{{ $transaction->organization->name ?? config('app.name') }}</div>
            <h1>SAVINGS RECEIPT</h1>
            <div class="receipt-title">Official Transaction Receipt</div>
        </div>

        <div class="receipt-info">
            <div>
                <div class="label">Receipt Number</div>
                <div class="value"><strong>{{ $transaction->transaction_number ?? 'N/A' }}</strong></div>
            </div>
            <div>
                <div class="label">Transaction Date</div>
                <div class="value">{{ $transaction->transaction_date?->format('d M Y') ?? 'N/A' }}</div>
            </div>
            <div>
                <div class="label">Status</div>
                <div class="value">
                    <span class="status-badge status-{{ strtolower($transaction->status->value ?? 'pending') }}">
                        {{ $transaction->status->label() ?? 'N/A' }}
                    </span>
                </div>
            </div>
        </div>

        <div class="receipt-amount">
            <div class="amount-label">Transaction Amount</div>
            <div class="amount-value">{{ number_format($transaction->amount ?? 0, 2) }}</div>
        </div>

        <div class="receipt-details">
            <table>
                <tr>
                    <td>Transaction Type</td>
                    <td>{{ $transaction->transaction_type?->label() ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Member Name</td>
                    <td>{{ $transaction->member->full_name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Member Number</td>
                    <td>{{ $transaction->member->member_number ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Savings Account</td>
                    <td>{{ $transaction->account->account_number ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Savings Product</td>
                    <td>{{ $transaction->account->product->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Balance Before</td>
                    <td>{{ number_format($transaction->balance_before ?? 0, 2) }}</td>
                </tr>
                <tr>
                    <td>Balance After</td>
                    <td>{{ number_format($transaction->balance_after ?? 0, 2) }}</td>
                </tr>
                <tr>
                    <td>Payment Method</td>
                    <td>{{ $transaction->paymentMethod->name ?? 'N/A' }}</td>
                </tr>
                @if($transaction->reference)
                <tr>
                    <td>Reference Number</td>
                    <td>{{ $transaction->reference }}</td>
                </tr>
                @endif
                @if($transaction->description)
                <tr>
                    <td>Description</td>
                    <td>{{ $transaction->description }}</td>
                </tr>
                @endif
                <tr>
                    <td>Organization</td>
                    <td>{{ $transaction->organization->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Branch</td>
                    <td>{{ $transaction->branch->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Group</td>
                    <td>{{ $transaction->vicobaGroup->name ?? 'N/A' }}</td>
                </tr>
            </table>
        </div>

        <div class="signature-area">
            <div class="sig-block">
                <div class="sig-line">Received By</div>
            </div>
            <div class="sig-block">
                <div class="sig-line">Member Signature</div>
            </div>
        </div>

        <div class="receipt-footer">
            <p>This is a computer-generated receipt. No signature is required for electronic transactions.</p>
            <p>Generated on {{ now()->format('d M Y H:i:s') }}</p>
            <div class="thank-you">Thank you for your savings!</div>
        </div>
    </div>

    @include('layouts.print.footer', [
        'text' => config('app.name', 'FinancePro') . ' · VICOBA System · ' . ($transaction->organization->name ?? '') . ' · Savings Receipt ' . ($transaction->transaction_number ?? 'N/A') . ' · Printed ' . now()->format('d M Y, H:i'),
    ])

</body>
</html>
