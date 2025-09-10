{{-- resources/views/auth/login.blade.php --}}
<x-guest-layout>
    <!-- Logo and Title Section -->
    <div class="text-center mb-4">
        <img src="{{ asset('images/pusdatin.jpg') }}" alt="Pusdatin Logo" class="mx-auto d-block mb-3" style="width: 256px; height: 256px; object-fit: contain;">
        <h1 class="h2 fw-bold mb-4" style="color: #1e40af;">Masuk ke Pusdatin Kemlu</h1>
    </div>

    <!-- Login Form -->
    <div class="card shadow-lg border-0">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('login') }}">
                @csrf

                <!-- Session Status -->
                <x-auth-session-status class="mb-3" :status="session('status')" />

                <!-- Email Address -->
                <div class="mb-3">
                    <label for="email" class="form-label fw-medium">{{ __('Email') }}</label>
                    <input 
                        id="email" 
                        class="form-control form-control-lg @error('email') is-invalid @enderror" 
                        type="email" 
                        name="email" 
                        value="{{ old('email') }}" 
                        required 
                        autofocus 
                        autocomplete="username"
                        placeholder="Masukkan email Anda">
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
                        autocomplete="current-password"
                        placeholder="Masukkan password Anda">
                    @error('password')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <!-- Remember Me -->
                <div class="mb-4">
                    <div class="form-check">
                        <input 
                            id="remember_me" 
                            type="checkbox" 
                            class="form-check-input" 
                            name="remember">
                        <label class="form-check-label text-muted" for="remember_me">
                            {{ __('Remember me') }}
                        </label>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="mb-3">
                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-semibold">
                        Masuk
                    </button>
                </div>

                <!-- Registration Link -->
                <div class="text-center">
                    <p class="mb-0 text-muted">Belum Punya Akun? 
                        <a href="{{ route('register') }}" class="text-decoration-none fw-semibold" style="color: #1e40af;">
                            Daftar Sekarang
                        </a>
                    </p>
                </div>
            </form>
        </div>
    </div>
</x-guest-layout>