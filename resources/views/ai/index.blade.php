@extends('layouts.app')

@section('title', 'AI Assistant - ' . config('app.name'))

@section('page-header')
    @include('layouts.components.page-header', [
        'title' => 'AI Assistant',
        'subtitle' => 'Ask FinancePro a question and get an answer grounded in your authorized data.',
    ])
@endsection

@section('content')
    @include('ai.partials.chat', ['suggestions' => $suggestions ?? []])
@endsection