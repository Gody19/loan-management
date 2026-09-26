@extends('layouts.member')

@php
    // The full chat UI is rendered on this page, so the floating widget must
    // not embed a second copy of the chat partial. A duplicate copy would
    // initialise a second chat script and double every submitted message.
    $hideAiWidget = true;
@endphp

@section('title', 'AI Assistant - FinancePro VICOBA')

@section('page-header')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 fw-semibold">AI Assistant</h1>
            <p class="text-muted mb-0" style="font-size: 0.875rem;">Ask about your loans, savings, shares and more — always from your own authorized data.</p>
        </div>
    </div>
@endsection

@section('content')
    @include('ai.partials.chat', ['suggestions' => $suggestions ?? [], 'autoOpen' => true])
@endsection