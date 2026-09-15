{{-- Reusable Form Components --}}

{{-- Form Wrapper --}}
@props(['method' => 'POST', 'action' => '', 'files' => false, 'class' => ''])

<form method="{{ $method }}" action="{{ $action }}" @if($files) enctype="multipart/form-data" @endif class="vicoba-form {{ $class }}">
    @csrf
    @if(in_array($method, ['PUT', 'PATCH', 'DELETE']))
        @method($method)
    @endif
    {{ $slot }}
</form>

{{-- Divider --}}
<hr class="my-4">
