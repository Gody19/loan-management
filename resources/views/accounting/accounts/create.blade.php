@extends('layouts.app')

@section('content')
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">Create Account</h4>
                <a href="{{ route('accounting.accounts.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <form action="{{ route('accounting.accounts.store') }}" method="POST">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="account_code" class="form-label">Account Code *</label>
                                <input type="text" class="form-control @error('account_code') is-invalid @enderror" name="account_code" id="account_code" value="{{ old('account_code') }}" required>
                                @error('account_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-8">
                                <label for="account_name" class="form-label">Account Name *</label>
                                <input type="text" class="form-control @error('account_name') is-invalid @enderror" name="account_name" id="account_name" value="{{ old('account_name') }}" required>
                                @error('account_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="account_type" class="form-label">Account Type *</label>
                                <select class="form-select @error('account_type') is-invalid @enderror" name="account_type" id="account_type" required>
                                    <option value="">Select Type</option>
                                    @foreach($accountTypes as $type)
                                        <option value="{{ $type->value }}" {{ old('account_type') === $type->value ? 'selected' : '' }}>{{ $type->label() }}</option>
                                    @endforeach
                                </select>
                                @error('account_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="parent_id" class="form-label">Parent Account</label>
                                <select class="form-select @error('parent_id') is-invalid @enderror" name="parent_id" id="parent_id">
                                    <option value="">None (Top Level)</option>
                                    @foreach($parentAccounts as $parent)
                                        <option value="{{ $parent->id }}" {{ old('parent_id') == $parent->id ? 'selected' : '' }}>{{ $parent->account_code }} - {{ $parent->account_name }}</option>
                                    @endforeach
                                </select>
                                @error('parent_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control @error('description') is-invalid @enderror" name="description" id="description" rows="2">{{ old('description') }}</textarea>
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-floppy me-1"></i> Create Account</button>
                            <a href="{{ route('accounting.accounts.index') }}" class="btn btn-outline-secondary ms-2">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
@endsection
