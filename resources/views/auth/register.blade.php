{{-- resources/views/auth/register.blade.php --}}
<x-guest-layout>
    <!-- Title Section -->
    <div class="text-center mb-4">
        <h1 class="h2 fw-bold mb-3" style="color: #1e40af;">Daftar Akun Baru</h1>
        <p class="text-muted">Buat akun untuk mengakses Pustik Kemlu</p>
    </div>

    <!-- Register Form -->
    <div class="card shadow-lg border-0">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('register') }}">
                @csrf

                <!-- Name -->
                <div class="mb-3">
                    <label for="name" class="form-label fw-medium">{{ __('Name') }}</label>
                    <input 
                        id="name" 
                        class="form-control form-control-lg @error('name') is-invalid @enderror" 
                        type="text" 
                        name="name" 
                        value="{{ old('name') }}" 
                        required 
                        autofocus 
                        autocomplete="name"
                        placeholder="Masukkan nama lengkap">
                    @error('name')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

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
                        autocomplete="username"
                        placeholder="Masukkan alamat email">
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
                        placeholder="Masukkan password">
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
                        placeholder="Konfirmasi password">
                    @error('password_confirmation')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <!-- Submit and Login Link -->
                <div class="d-flex flex-column gap-3">
                    <button type="submit" class="btn btn-primary btn-lg fw-semibold">
                        {{ __('Register') }}
                    </button>
                    
                    <div class="text-center">
                        <a class="text-decoration-none text-muted" href="{{ route('login') }}">
                            {{ __('Already registered?') }}
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-guest-layout>