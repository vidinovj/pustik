{{-- resources/views/components/auth-session-status.blade.php --}}
@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'alert alert-success']) }} role="alert">
        {{ $status }}
    </div>
@endif
