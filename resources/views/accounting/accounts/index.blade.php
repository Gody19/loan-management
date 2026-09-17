@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Chart of Accounts</h4>
        <a href="{{ route('accounting.accounts.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> Add Account
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <ul class="nav nav-tabs mb-3">
                @foreach($accountsByType as $type => $typeAccounts)
                    <li class="nav-item">
                        <a class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" href="#tab-{{ $type }}">
                            {{ ucfirst($type) }} ({{ $typeAccounts->count() }})
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="tab-content">
                @foreach($accountsByType as $type => $typeAccounts)
                    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="tab-{{ $type }}">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Normal Balance</th>
                                        <th>Status</th>
                                        <th width="150">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($typeAccounts as $account)
                                        <tr>
                                            <td><code>{{ $account->account_code }}</code></td>
                                            <td>
                                                @if($account->parent)
                                                    <span class="text-muted ms-3">└─</span>
                                                @endif
                                                {{ $account->account_name }}
                                            </td>
                                            <td>{{ $account->description ?? '-' }}</td>
                                            <td><span class="badge bg-{{ $account->account_type->color() }}">{{ ucfirst($account->normal_balance) }}</span></td>
                                            <td>
                                                @if($account->is_system)
                                                    <span class="badge bg-info">System</span>
                                                @elseif($account->is_active)
                                                    <span class="badge bg-success">Active</span>
                                                @else
                                                    <span class="badge bg-secondary">Inactive</span>
                                                @endif
                                            </td>
                                            <td>
                                                <a href="{{ route('accounting.accounts.show', $account->id) }}" class="btn btn-sm btn-outline-primary" title="View"><i class="fas fa-eye"></i></a>
                                                <a href="{{ route('accounting.accounts.edit', $account->id) }}" class="btn btn-sm btn-outline-warning" title="Edit"><i class="fas fa-edit"></i></a>
                                                @unless($account->is_system)
                                                    <form action="{{ route('accounting.accounts.toggle', $account->id) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-{{ $account->is_active ? 'secondary' : 'success' }}" title="{{ $account->is_active ? 'Deactivate' : 'Activate' }}">
                                                            <i class="fas fa-{{ $account->is_active ? 'ban' : 'check' }}"></i>
                                                        </button>
                                                    </form>
                                                    <form action="{{ route('accounting.accounts.destroy', $account->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this account?')">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                                    </form>
                                                @endunless
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center text-muted">No {{ strtolower($type) }} accounts.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
