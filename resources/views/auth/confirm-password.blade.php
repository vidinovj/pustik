{{-- resources/views/auth/confirm-password.blade.php --}}
<x-guest-layout>
    <!-- Title Section -->
    <div class="text-center mb-4">
        <h1 class="h2 fw-bold mb-3" style="color: #1e40af;">Konfirmasi Password</h1>
    </div>

    <!-- Confirm Password Form -->
    <div class="card shadow-lg border-0">
        <div class="card-body p-4">
            <div class="mb-4 text-muted">
                {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
            </div>

            <form method="POST" action="{{ route('password.confirm') }}">
                @csrf

                <!-- Password -->
                <div class="mb-4">
                    <label for="password" class="form-label fw-medium">{{ __('Password') }}</label>
                    <input 
                        id="password" 
                        class="form-control form-control-lg @error('password') is-invalid @enderror"
                        type="password"
                        name="password"
                        required 
                        autocomplete="current-password"
                        placeholder="Masukkan password Anda">
                    @error('password')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100 fw-semibold">
                    {{ __('Confirm') }}
                </button>
            </form>
        </div>
    </div>
</x-guest-layout>