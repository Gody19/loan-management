@extends('layouts.app')

@php
    // The full chat UI is rendered on this page, so the floating widget must
    // not embed a second copy of the chat partial. A duplicate copy would
    // initialise a second chat script and double every submitted message.
    $hideAiWidget = true;
@endphp

@section('title', 'AI Assistant - ' . config('app.name'))

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'AI Assistant',
        'subtitle' => 'Ask FinancePro a question and get an answer grounded in your authorized data.',
    ])
@endsection

@section('content')
    @include('ai.partials.chat', ['suggestions' => $suggestions ?? [], 'autoOpen' => true])
@endsection