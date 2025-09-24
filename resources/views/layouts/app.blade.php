<!-- resources/views/layouts/app.blade.php -->
<x-layout>
    <x-slot:title>Admin Panel</x-slot:title>

    <div class="container-fluid mt-4">
        @yield('content')
    </div>

    @stack('scripts')
</x-layout>