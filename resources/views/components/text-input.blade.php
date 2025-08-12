{{-- resources/views/components/text-input.blade.php --}}
@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'form-control']) }}>
