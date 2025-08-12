{{-- resources/views/auth/forgot-password.blade.php --}}
<x-guest-layout>
    <!-- Title Section -->
    <div class="text-center mb-4">
        <h1 class="h2 fw-bold mb-3" style="color: #1e40af;">Reset Password</h1>
    </div>

    <!-- Forgot Password Form -->
    <div class="card shadow-lg border-0">
        <div class="card-body p-4">
            <div class="mb-4 text-muted">
                {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
            </div>

            <!-- Session Status -->
            <x-auth-session-status class="mb-4" :status="session('status')" />

            <form method="POST" action="{{ route('password.email') }}">
                @csrf

                <!-- Email Address -->
                <div class="mb-4">
                    <label for="email" class="form-label fw-medium">{{ __('Email') }}</label>
                    <input 
                        id="email" 
                        class="form-control form-control-lg @error('email') is-invalid @enderror" 
                        type="email" 
                        name="email" 
                        value="{{ old('email') }}" 
                        required 
                        autofocus
                        placeholder="Masukkan alamat email">
                    @error('email')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="d-flex flex-column gap-3">
                    <button type="submit" class="btn btn-primary btn-lg fw-semibold">
                        {{ __('Email Password Reset Link') }}
                    </button>
                    
                    <div class="text-center">
                        <a class="text-decoration-none text-muted" href="{{ route('login') }}">
                            Kembali ke Login
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-guest-layout>