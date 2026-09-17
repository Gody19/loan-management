@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <h4 class="mb-4">Account Configuration</h4>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="card shadow-sm">
        <div class="card-header">
            <h6 class="mb-0">Account Mappings</h6>
        </div>
        <div class="card-body">
            <form action="{{ route('accounting.configuration.update') }}" method="POST">
                @csrf @method('PUT')
                <div class="row g-4">
                    @foreach($mappings as $key => $mapping)
                        <div class="col-md-6">
                            <label class="form-label fw-bold">{{ $mapping['label'] }}</label>
                            <select name="mappings[{{ $key }}]" class="form-select">
                                <option value="">-- Select Account --</option>
                                @php
                                    $accounts = \App\Models\ChartOfAccount::where('organization_id', $organization->id)->active()->orderBy('account_code')->get();
                                @endphp
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}" {{ $mapping['account'] && $mapping['account']->id === $account->id ? 'selected' : '' }}>
                                        {{ $account->account_code }} - {{ $account->account_name }}
                                    </option>
                                @endforeach
                            </select>
                            @if($mapping['account'])
                                <small class="text-success"><i class="fas fa-check-circle me-1"></i> Mapped to: {{ $mapping['account']->account_code }} - {{ $mapping['account']->account_name }}</small>
                            @else
                                <small class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i> Not configured</small>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Mappings</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
