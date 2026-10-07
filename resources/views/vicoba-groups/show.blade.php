@extends('layouts.app')

@section('title', $group->name . ' - ' . config('app.name'))

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => $group->name,
        'subtitle' => 'VICOBA Group Details',
        'breadcrumb' => [
            ['label' => 'VICOBA Groups', 'url' => route('vicoba-groups.index')],
            ['label' => $group->name],
        ],
        'actions' => '<button type="button" onclick="window.print()" class="btn btn-outline-secondary no-print"><i class="bi bi-printer me-1"></i> Print</button>'
            . '<a href="' . route('vicoba-groups.edit', $group) . '" class="btn btn-primary vicoba-btn"><i class="bi bi-pencil me-1"></i> Edit</a>',
    ])
@endsection

@section('content')

<div class="no-print">
    {{-- Key figures --}}
    <div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-3 mb-4">
        <div class="col">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <small class="text-muted d-block">Total Members</small>
                    <div class="fw-bold fs-5">{{ $summary['members']['total'] }}</div>
                    <small class="text-success">{{ $summary['members']['active'] }} active</small>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <small class="text-muted d-block">Savings Balance</small>
                    <div class="fw-bold fs-5">TSh {{ number_format($summary['financial']['savings_balance'], 2) }}</div>
                    <small class="text-muted">Across all members</small>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <small class="text-muted d-block">Shares Value</small>
                    <div class="fw-bold fs-5">TSh {{ number_format($summary['financial']['shares_value'], 2) }}</div>
                    <small class="text-muted">{{ number_format($summary['financial']['shares_count']) }} shares</small>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <small class="text-muted d-block">Welfare Balance</small>
                    <div class="fw-bold fs-5">TSh {{ number_format($summary['financial']['welfare_balance'], 2) }}</div>
                    <small class="text-muted">Group welfare fund</small>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card vicoba-card h-100">
                <div class="card-body">
                    <small class="text-muted d-block">Outstanding Balance</small>
                    <div class="fw-bold fs-5 text-danger">TSh {{ number_format($summary['loans']['outstanding'], 2) }}</div>
                    <small class="text-muted">Unpaid loan balance</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card vicoba-card">
                <div class="card-header">
                    <h6 class="mb-0 fw-semibold">Information</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3" style="font-size: 0.9rem;">
                        <div class="col-md-6">
                            <span class="text-muted d-block">Group Code</span>
                            <span class="fw-medium"><code>{{ $group->code }}</code></span>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted d-block">Status</span>
                            <span class="badge bg-{{ $group->status->color() }}">{{ $group->status->label() }}</span>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted d-block">Branch</span>
                            <a href="{{ route('branches.show', $group->branch) }}">{{ $group->branch->name }}</a>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted d-block">Organization</span>
                            <span>{{ $group->branch->organization->name }}</span>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted d-block">Meeting Day</span>
                            <span>{{ $group->meeting_day ?? '-' }}</span>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted d-block">Meeting Time</span>
                            <span>{{ $group->meeting_time ?? '-' }}</span>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted d-block">Meeting Location</span>
                            <span>{{ $group->meeting_location ?? '-' }}</span>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted d-block">Created</span>
                            <span>{{ $group->created_at->format('M d, Y') }}</span>
                        </div>
                        @if($group->description)
                            <div class="col-md-12">
                                <span class="text-muted d-block">Description</span>
                                <span>{{ $group->description }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card vicoba-card h-100">
                <div class="card-header">
                    <h6 class="mb-0 fw-semibold">Income &amp; Expenses</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Interest collected</span>
                        <span class="fw-semibold text-success">TSh {{ number_format($summary['income']['interest'], 2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Fees collected</span>
                        <span class="fw-semibold text-success">TSh {{ number_format($summary['income']['fees'], 2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 pt-2 border-top">
                        <span class="fw-semibold">Total income</span>
                        <span class="fw-bold text-success">TSh {{ number_format($summary['income']['total'], 2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Welfare payouts</span>
                        <span class="fw-semibold text-danger">TSh {{ number_format($summary['expenses']['welfare_paid'], 2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 pt-2 border-top">
                        <span class="fw-semibold">Total expenses</span>
                        <span class="fw-bold text-danger">TSh {{ number_format($summary['expenses']['total'], 2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between pt-2 border-top">
                        <span class="fw-semibold">Net result</span>
                        <span class="fw-bold {{ $summary['net_result'] >= 0 ? 'text-success' : 'text-danger' }}">
                            TSh {{ number_format($summary['net_result'], 2) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card vicoba-card h-100">
                <div class="card-header">
                    <h6 class="mb-0 fw-semibold">Loan Portfolio</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge bg-primary">{{ $summary['loans']['active'] }} Active</span>
                        <span class="badge bg-warning text-dark">{{ $summary['loans']['pending'] }} Pending</span>
                        <span class="badge bg-secondary">{{ $summary['loans']['completed'] }} Completed</span>
                        <span class="badge bg-dark">{{ $summary['loans']['cancelled'] }} Cancelled</span>
                        <span class="badge bg-info text-dark">{{ $summary['loans']['total'] }} Total</span>
                    </div>
                    <table class="table table-sm mb-0" style="font-size: 0.9rem;">
                        <tbody>
                            <tr>
                                <td class="text-muted">Principal disbursed</td>
                                <td class="text-end fw-semibold">TSh {{ number_format($summary['loans']['principal'], 2) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Total repaid</td>
                                <td class="text-end fw-semibold text-success">TSh {{ number_format($summary['loans']['repaid'], 2) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Outstanding balance</td>
                                <td class="text-end fw-semibold text-danger">TSh {{ number_format($summary['loans']['outstanding'], 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card vicoba-card h-100">
                <div class="card-header">
                    <h6 class="mb-0 fw-semibold">Loan Applications</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge bg-warning text-dark">{{ $summary['applications']['pending'] }} Pending</span>
                        <span class="badge bg-success">{{ $summary['applications']['approved'] }} Approved</span>
                        <span class="badge bg-danger">{{ $summary['applications']['rejected'] }} Rejected</span>
                        <span class="badge bg-info text-dark">{{ $summary['applications']['total'] }} Total</span>
                    </div>
                    @if ($summary['applications']['by_status']->isEmpty())
                        <p class="text-muted mb-0" style="font-size: 0.9rem;">No loan applications recorded for this group.</p>
                    @else
                        <table class="table table-sm mb-0" style="font-size: 0.9rem;">
                            <tbody>
                                @foreach ($summary['applications']['by_status'] as $status => $count)
                                    @php($applicationStatus = \App\Enums\LoanApplicationStatus::tryFrom($status))
                                    <tr>
                                        <td>
                                            <span class="badge bg-{{ $applicationStatus?->color() ?? 'secondary' }}">
                                                {{ $applicationStatus?->label() ?? ucfirst($status) }}
                                            </span>
                                        </td>
                                        <td class="text-end fw-semibold">{{ $count }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card vicoba-card h-100">
                <div class="card-header">
                    <h6 class="mb-0 fw-semibold">Members by Status</h6>
                </div>
                <div class="card-body">
                    @if ($summary['members']['by_status']->isEmpty())
                        <p class="text-muted mb-0">No members registered in this group yet.</p>
                    @else
                        <table class="table table-sm mb-0" style="font-size: 0.9rem;">
                            <tbody>
                                @foreach ($summary['members']['by_status'] as $status => $count)
                                    @php($memberStatus = \App\Enums\MemberStatus::tryFrom($status))
                                    <tr>
                                        <td>
                                            <span class="badge bg-{{ $memberStatus?->color() ?? 'secondary' }}">
                                                {{ $memberStatus?->label() ?? ucfirst($status) }}
                                            </span>
                                        </td>
                                        <td class="text-end fw-semibold">{{ $count }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card vicoba-card h-100">
                <div class="card-header">
                    <h6 class="mb-0 fw-semibold">Savings Cash Flow</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Total deposits</span>
                        <span class="fw-semibold text-success">TSh {{ number_format($summary['financial']['deposits'], 2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Total withdrawals</span>
                        <span class="fw-semibold text-danger">TSh {{ number_format($summary['financial']['withdrawals'], 2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between pt-2 border-top">
                        <span class="fw-semibold">Net inflow</span>
                        <span class="fw-bold {{ ($summary['financial']['deposits'] - $summary['financial']['withdrawals']) >= 0 ? 'text-success' : 'text-danger' }}">
                            TSh {{ number_format($summary['financial']['deposits'] - $summary['financial']['withdrawals'], 2) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>{{-- /.no-print --}}

{{-- Print-only document: rendered exclusively by the print media query --}}
<div class="print-document">
    @include('layouts.print.watermark')

    @include('layouts.print.header', [
        'organization' => $group->branch->organization->name,
        'meta' => [
            $group->branch->name,
            'Generated ' . now()->format('d M Y, H:i'),
        ],
        'title' => 'VICOBA Group Details',
    ])

    <table class="print-table print-doc-details">
        <tbody>
            <tr>
                <th>Group Name</th>
                <td>{{ $group->name }}</td>
                <th>Group Code</th>
                <td>{{ $group->code }}</td>
            </tr>
            <tr>
                <th>Status</th>
                <td>{{ $group->status->label() }}</td>
                <th>Members</th>
                <td>{{ $summary['members']['total'] }} ({{ $summary['members']['active'] }} active)</td>
            </tr>
            <tr>
                <th>Organization</th>
                <td>{{ $group->branch->organization->name }}</td>
                <th>Branch</th>
                <td>{{ $group->branch->name }}</td>
            </tr>
            <tr>
                <th>Meeting Day</th>
                <td>{{ $group->meeting_day ?? '—' }}</td>
                <th>Meeting Time</th>
                <td>{{ $group->meeting_time ?? '—' }}</td>
            </tr>
            <tr>
                <th>Meeting Location</th>
                <td>{{ $group->meeting_location ?? '—' }}</td>
                <th>Created</th>
                <td>{{ $group->created_at->format('d M Y') }}</td>
            </tr>
            @if ($group->description)
                <tr>
                    <th>Description</th>
                    <td colspan="3">{{ $group->description }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <h2 class="print-section-title">Financial Position</h2>
    <table class="print-table print-summary">
        <thead>
            <tr>
                <th>Savings Balance</th>
                <th>Shares Value</th>
                <th>Welfare Balance</th>
                <th>Outstanding Balance</th>
                <th>Total Members</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>TSh {{ number_format($summary['financial']['savings_balance'], 2) }}</td>
                <td>TSh {{ number_format($summary['financial']['shares_value'], 2) }}</td>
                <td>TSh {{ number_format($summary['financial']['welfare_balance'], 2) }}</td>
                <td>TSh {{ number_format($summary['loans']['outstanding'], 2) }}</td>
                <td>{{ $summary['members']['total'] }}</td>
            </tr>
        </tbody>
    </table>

    <h2 class="print-section-title">Loan Portfolio</h2>
    <table class="print-table">
        <thead>
            <tr>
                <th>Loan Status</th>
                <th class="text-end">Loans</th>
                <th class="text-end">Principal Disbursed</th>
                <th class="text-end">Total Repaid</th>
                <th class="text-end">Outstanding Balance</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Active (disbursed + running)</td>
                <td class="text-end">{{ $summary['loans']['active'] }}</td>
                <td class="text-end" rowspan="4" style="vertical-align: middle;">TSh {{ number_format($summary['loans']['principal'], 2) }}</td>
                <td class="text-end" rowspan="4" style="vertical-align: middle;">TSh {{ number_format($summary['loans']['repaid'], 2) }}</td>
                <td class="text-end" rowspan="4" style="vertical-align: middle;">TSh {{ number_format($summary['loans']['outstanding'], 2) }}</td>
            </tr>
            <tr>
                <td>Pending (approved / awaiting disbursement)</td>
                <td class="text-end">{{ $summary['loans']['pending'] }}</td>
            </tr>
            <tr>
                <td>Completed (fully repaid)</td>
                <td class="text-end">{{ $summary['loans']['completed'] }}</td>
            </tr>
            <tr>
                <td>Cancelled</td>
                <td class="text-end">{{ $summary['loans']['cancelled'] }}</td>
            </tr>
            <tr>
                <th>Total</th>
                <th class="text-end">{{ $summary['loans']['total'] }}</th>
                <th class="text-end">—</th>
                <th class="text-end">—</th>
                <th class="text-end">—</th>
            </tr>
        </tbody>
    </table>

    <h2 class="print-section-title">Loan Applications</h2>
    @if ($summary['applications']['by_status']->isEmpty())
        <p class="print-empty">No loan applications recorded for this group.</p>
    @else
        <table class="print-table">
            <thead>
                <tr>
                    <th>Application Status</th>
                    <th class="text-end">Applications</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($summary['applications']['by_status'] as $status => $count)
                    @php($applicationStatus = \App\Enums\LoanApplicationStatus::tryFrom($status))
                    <tr>
                        <td>{{ $applicationStatus?->label() ?? ucfirst($status) }}</td>
                        <td class="text-end">{{ $count }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th>Total</th>
                    <th class="text-end">{{ $summary['applications']['total'] }}</th>
                </tr>
            </tfoot>
        </table>
    @endif

    <h2 class="print-section-title">Members by Status</h2>
    @if ($summary['members']['by_status']->isEmpty())
        <p class="print-empty">No members registered in this group yet.</p>
    @else
        <table class="print-table">
            <thead>
                <tr>
                    <th>Member Status</th>
                    <th class="text-end">Members</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($summary['members']['by_status'] as $status => $count)
                    @php($memberStatus = \App\Enums\MemberStatus::tryFrom($status))
                    <tr>
                        <td>{{ $memberStatus?->label() ?? ucfirst($status) }}</td>
                        <td class="text-end">{{ $count }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th>Total</th>
                    <th class="text-end">{{ $summary['members']['total'] }}</th>
                </tr>
            </tfoot>
        </table>
    @endif

    <h2 class="print-section-title">Income &amp; Expenses</h2>
    <table class="print-table">
        <thead>
            <tr>
                <th>Item</th>
                <th class="text-end">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Interest collected on group loans</td>
                <td class="text-end">TSh {{ number_format($summary['income']['interest'], 2) }}</td>
            </tr>
            <tr>
                <td>Fees collected on group loans</td>
                <td class="text-end">TSh {{ number_format($summary['income']['fees'], 2) }}</td>
            </tr>
            <tr>
                <th>Total income</th>
                <th class="text-end">TSh {{ number_format($summary['income']['total'], 2) }}</th>
            </tr>
            <tr>
                <td>Welfare benefits paid out</td>
                <td class="text-end">TSh {{ number_format($summary['expenses']['welfare_paid'], 2) }}</td>
            </tr>
            <tr>
                <th>Total expenses</th>
                <th class="text-end">TSh {{ number_format($summary['expenses']['total'], 2) }}</th>
            </tr>
            <tr>
                <th>Net result</th>
                <th class="text-end">TSh {{ number_format($summary['net_result'], 2) }}</th>
            </tr>
        </tbody>
    </table>

    <h2 class="print-section-title">Savings Cash Flow</h2>
    <table class="print-table">
        <thead>
            <tr>
                <th>Item</th>
                <th class="text-end">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Total deposits (completed)</td>
                <td class="text-end">TSh {{ number_format($summary['financial']['deposits'], 2) }}</td>
            </tr>
            <tr>
                <td>Total withdrawals (completed)</td>
                <td class="text-end">TSh {{ number_format($summary['financial']['withdrawals'], 2) }}</td>
            </tr>
            <tr>
                <th>Net inflow</th>
                <th class="text-end">TSh {{ number_format($summary['financial']['deposits'] - $summary['financial']['withdrawals'], 2) }}</th>
            </tr>
        </tbody>
    </table>

    @include('layouts.print.footer', [
        'text' => config('app.name', 'FinancePro') . ' · VICOBA System · ' . $group->branch->organization->name . ' · ' . $group->name . ' (' . $group->code . ') · Printed ' . now()->format('d M Y, H:i'),
    ])
</div>

@endsection

@push('styles')
@include('layouts.print.styles')
@endpush
