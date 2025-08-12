{{-- resources/views/auth/reset-password.blade.php --}}
<x-guest-layout>
    <!-- Title Section -->
    <div class="text-center mb-4">
        <h1 class="h2 fw-bold mb-3" style="color: #1e40af;">Reset Password</h1>
        <p class="text-muted">Masukkan password baru untuk akun Anda</p>
    </div>

    <!-- Reset Password Form -->
    <div class="card shadow-lg border-0">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('password.store') }}">
                @csrf

                <!-- Password Reset Token -->
                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                <!-- Email Address -->
                <div class="mb-3">
                    <label for="email" class="form-label fw-medium">{{ __('Email') }}</label>
                    <input 
                        id="email" 
                        class="form-control form-control-lg @error('email') is-invalid @enderror" 
                        type="email" 
                        name="email" 
                        value="{{ old('email', $request->email) }}" 
                        required 
                        autofocus 
                        autocomplete="username"
                        placeholder="Email address">
                    @error('email')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <!-- Password -->
                <div class="mb-3">
                    <label for="password" class="form-label fw-medium">{{ __('Password') }}</label>
                    <input 
                        id="password" 
                        class="form-control form-control-lg @error('password') is-invalid @enderror" 
                        type="password" 
                        name="password" 
                        required 
                        autocomplete="new-password"
                        placeholder="Password baru">
                    @error('password')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <!-- Confirm Password -->
                <div class="mb-4">
                    <label for="password_confirmation" class="form-label fw-medium">{{ __('Confirm Password') }}</label>
                    <input 
                        id="password_confirmation" 
                        class="form-control form-control-lg @error('password_confirmation') is-invalid @enderror"
                        type="password"
                        name="password_confirmation" 
                        required 
                        autocomplete="new-password"
                        placeholder="Konfirmasi password baru">
                    @error('password_confirmation')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100 fw-semibold">
                    {{ __('Reset Password') }}
                </button>
            </form>
        </div>
    </div>
</x-guest-layout>