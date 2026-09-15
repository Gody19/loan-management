{{-- Reusable Table Component --}}
@props([
    'striped' => true,
    'hover' => true,
    'bordered' => false,
    'compact' => false,
])

<div class="table-responsive">
    <table class="table vicoba-table {{ $striped ? 'table-striped' : '' }} {{ $hover ? 'table-hover' : '' }} {{ $bordered ? 'table-bordered' : '' }} {{ $compact ? 'table-sm' : '' }} mb-0">
        {{ $slot }}
    </table>
</div>
