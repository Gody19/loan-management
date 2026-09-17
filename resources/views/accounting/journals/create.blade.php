@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">Create Journal Entry</h4>
                <a href="{{ route('accounting.journals.index') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
            </div>

            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <div class="card shadow-sm">
                <div class="card-body">
                    <form action="{{ route('accounting.journals.store') }}" method="POST" id="journalForm">
                        @csrf
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="entry_date" class="form-label">Entry Date *</label>
                                <input type="date" class="form-control" name="entry_date" id="entry_date" value="{{ old('entry_date', date('Y-m-d')) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label for="description" class="form-label">Description *</label>
                                <input type="text" class="form-control" name="description" id="description" value="{{ old('description') }}" required>
                            </div>
                        </div>

                        <h6 class="mb-3">Journal Lines</h6>
                        <div class="table-responsive">
                            <table class="table" id="linesTable">
                                <thead class="table-light">
                                    <tr>
                                        <th width="250">Account *</th>
                                        <th>Description</th>
                                        <th width="150">Debit (TSh)</th>
                                        <th width="150">Credit (TSh)</th>
                                        <th width="50"></th>
                                    </tr>
                                </thead>
                                <tbody id="linesBody">
                                    <tr class="line-row">
                                        <td>
                                            <select name="lines[0][chart_of_account_id]" class="form-select form-select-sm" required>
                                                <option value="">Select Account</option>
                                                @foreach($accounts as $account)
                                                    <option value="{{ $account->id }}">{{ $account->account_code }} - {{ $account->account_name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td><input type="text" name="lines[0][description]" class="form-control form-control-sm" placeholder="Optional"></td>
                                        <td><input type="number" name="lines[0][debit]" class="form-control form-control-sm debit-input" step="0.01" min="0" value="0"></td>
                                        <td><input type="number" name="lines[0][credit]" class="form-control form-control-sm credit-input" step="0.01" min="0" value="0"></td>
                                        <td><button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="fas fa-times"></i></button></td>
                                    </tr>
                                    <tr class="line-row">
                                        <td>
                                            <select name="lines[1][chart_of_account_id]" class="form-select form-select-sm" required>
                                                <option value="">Select Account</option>
                                                @foreach($accounts as $account)
                                                    <option value="{{ $account->id }}">{{ $account->account_code }} - {{ $account->account_name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td><input type="text" name="lines[1][description]" class="form-control form-control-sm" placeholder="Optional"></td>
                                        <td><input type="number" name="lines[1][debit]" class="form-control form-control-sm debit-input" step="0.01" min="0" value="0"></td>
                                        <td><input type="number" name="lines[1][credit]" class="form-control form-control-sm credit-input" step="0.01" min="0" value="0"></td>
                                        <td><button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="fas fa-times"></i></button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="addLine"><i class="fas fa-plus me-1"></i> Add Line</button>
                            <div>
                                <span class="me-3">Total Debit: <strong id="totalDebit" class="text-primary">TSh 0.00</strong></span>
                                <span>Total Credit: <strong id="totalCredit" class="text-primary">TSh 0.00</strong></span>
                                <span id="balanceStatus" class="ms-3"></span>
                            </div>
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary" id="submitBtn"><i class="fas fa-save me-1"></i> Create Draft</button>
                            <a href="{{ route('accounting.journals.index') }}" class="btn btn-outline-secondary ms-2">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let lineIndex = 2;
    const accountsHtml = `{!! $accounts->map(fn($a) => '<option value="'.$a->id.'">'.$a->account_code.' - '.$a->account_name.'</option>')->implode("\n") !!}`;

    document.getElementById('addLine').addEventListener('click', function() {
        const row = `<tr class="line-row">
            <td><select name="lines[${lineIndex}][chart_of_account_id]" class="form-select form-select-sm" required><option value="">Select Account</option>${accountsHtml}</select></td>
            <td><input type="text" name="lines[${lineIndex}][description]" class="form-control form-control-sm" placeholder="Optional"></td>
            <td><input type="number" name="lines[${lineIndex}][debit]" class="form-control form-control-sm debit-input" step="0.01" min="0" value="0"></td>
            <td><input type="number" name="lines[${lineIndex}][credit]" class="form-control form-control-sm credit-input" step="0.01" min="0" value="0"></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="fas fa-times"></i></button></td>
        </tr>`;
        document.getElementById('linesBody').insertAdjacentHTML('beforeend', row);
        lineIndex++;
    });

    document.getElementById('linesBody').addEventListener('click', function(e) {
        if (e.target.closest('.remove-line')) {
            const rows = document.querySelectorAll('.line-row');
            if (rows.length > 2) {
                e.target.closest('.line-row').remove();
                updateTotals();
            }
        }
    });

    document.getElementById('linesBody').addEventListener('input', updateTotals);

    function updateTotals() {
        let totalDebit = 0, totalCredit = 0;
        document.querySelectorAll('.debit-input').forEach(el => totalDebit += parseFloat(el.value) || 0);
        document.querySelectorAll('.credit-input').forEach(el => totalCredit += parseFloat(el.value) || 0);

        document.getElementById('totalDebit').textContent = 'TSh ' + totalDebit.toLocaleString(undefined, {minimumFractionDigits: 2});
        document.getElementById('totalCredit').textContent = 'TSh ' + totalCredit.toLocaleString(undefined, {minimumFractionDigits: 2});

        const status = document.getElementById('balanceStatus');
        if (Math.abs(totalDebit - totalCredit) < 0.01 && totalDebit > 0) {
            status.innerHTML = '<span class="badge bg-success">Balanced</span>';
        } else {
            status.innerHTML = '<span class="badge bg-danger">Unbalanced</span>';
        }
    }
});
</script>
@endpush
