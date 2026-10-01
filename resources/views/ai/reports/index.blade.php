@php
    /**
     * A single datum renderer shared by every report view so a value can never
     * appear without its classification label and its authoritative source.
     */
    $classificationMeta = [
        'fact' => ['label' => 'Fact', 'class' => 'bg-primary', 'icon' => 'bi-clipboard-data'],
        'trend' => ['label' => 'Trend', 'class' => 'bg-info text-dark', 'icon' => 'bi-graph-up-arrow'],
        'prediction' => ['label' => 'Prediction', 'class' => 'bg-warning text-dark', 'icon' => 'bi-graph-up'],
        'advisory' => ['label' => 'Advisory', 'class' => 'bg-secondary', 'icon' => 'bi-lightbulb'],
    ];
@endphp

@extends('layouts.app')

@section('title', 'Management Intelligence Reports - ' . config('app.name'))

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'Management Intelligence Reports',
        'subtitle' => 'Structured management reporting over your authorized organizations. Every figure is computed deterministically; the AI only explains it.',
    ])
@endsection

@section('content')
    {{--
        Management intelligence reporting (Phase 12.0).

        Read-only analytical surface. The tenant is derived server-side from the
        trusted context; the branch selector only ever offers branches the user
        is actually assigned to. No input on this page can change a financial
        record, a loan decision, a prediction or an insight.
    --}}

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="vicoba-card">
                <div class="card-header"><i class="bi bi-file-earmark-bar-graph me-1"></i> Generate a report</div>
                <div class="card-body">
                    @if (count($reportTypes) === 0)
                        <div class="alert alert-warning mb-0">
                            You do not have permission to generate management intelligence reports.
                        </div>
                    @else
                        <form method="POST" action="{{ route('ai.reports.store') }}">
                            @csrf

                            <div class="mb-3">
                                <label for="report_type" class="form-label">Report type</label>
                                <select name="report_type" id="report_type" class="form-select @error('report_type') is-invalid @enderror" required>
                                    @foreach ($reportTypes as $type)
                                        <option value="{{ $type->value }}" @selected(old('report_type') === $type->value)>
                                            {{ $type->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('report_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">
                                    Accounting reporting additionally requires the accounting capability.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="period" class="form-label">Reporting period</label>
                                <select name="period" id="period" class="form-select @error('period') is-invalid @enderror" required>
                                    @foreach ($periods as $period)
                                        <option value="{{ $period->value }}" @selected(old('period') === $period->value)>
                                            {{ $period->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('period')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label for="from" class="form-label">From <span class="text-muted small">(custom)</span></label>
                                    <input type="date" name="from" id="from" value="{{ old('from') }}" class="form-control @error('from') is-invalid @enderror">
                                    @error('from')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-6">
                                    <label for="to" class="form-label">To <span class="text-muted small">(custom)</span></label>
                                    <input type="date" name="to" id="to" value="{{ old('to') }}" class="form-control @error('to') is-invalid @enderror">
                                    @error('to')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            @if (count($branches) > 0)
                                <div class="mb-3">
                                    <label for="branch_id" class="form-label">Branch <span class="text-muted small">(optional)</span></label>
                                    <select name="branch_id" id="branch_id" class="form-select">
                                        <option value="">All my authorized branches</option>
                                        @foreach ($branches as $branch)
                                            <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">Only branches assigned to you are listed.</div>
                                </div>
                            @endif

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="with_narrative" value="1" id="with_narrative" @checked(old('with_narrative'))>
                                <label class="form-check-label" for="with_narrative">
                                    Include the optional AI narrative explanation
                                </label>
                                <div class="form-text">
                                    Optional. If the AI provider is unavailable the financial report is still delivered in full.
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-cpu me-1"></i> Generate report
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="vicoba-card">
                <div class="card-header"><i class="bi bi-clock-history me-1"></i> Recent reports</div>
                <div class="card-body">
                    @if ($recent->isEmpty())
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-inbox d-block mb-2" style="font-size: 1.8rem;"></i>
                            No reports have been generated yet.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Report</th>
                                        <th>Period</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recent as $item)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $item->report_type->label() }}</div>
                                                <div class="small text-muted">
                                                    {{ $item->branch?->name ?? 'All authorized branches' }}
                                                    &middot; data through {{ $item->data_through?->toDateString() }}
                                                </div>
                                            </td>
                                            <td class="small">
                                                {{ $item->period_start?->toDateString() }}<br>
                                                <span class="text-muted">to {{ $item->period_end?->toDateString() }}</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ $item->status->color() }}">{{ $item->status->label() }}</span>
                                            </td>
                                            <td class="text-end">
                                                @if ($item->status->isReportable())
                                                    <a href="{{ route('ai.reports.show', $item) }}" class="btn btn-sm btn-outline-primary">View</a>
                                                @else
                                                    <span class="small text-muted">{{ $item->failure_reason ?? 'Not readable' }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection