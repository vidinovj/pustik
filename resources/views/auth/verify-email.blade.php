{{-- resources/views/auth/verify-email.blade.php --}}
<x-guest-layout>
    <!-- Title Section -->
    <div class="text-center mb-4">
        <h1 class="h2 fw-bold mb-3" style="color: #1e40af;">Verifikasi Email</h1>
    </div>

    <!-- Verify Email Form -->
    <div class="card shadow-lg border-0">
        <div class="card-body p-4">
            <div class="mb-4 text-muted">
                {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
            </div>

            @if (session('status') == 'verification-link-sent')
                <div class="alert alert-success mb-4" role="alert">
                    {{ __('A new verification link has been sent to the email address you provided during registration.') }}
                </div>
            @endif

            <div class="d-grid gap-3">
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-semibold">
                        {{ __('Resend Verification Email') }}
                    </button>
                </form>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary btn-lg w-100">
                        {{ __('Log Out') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-guest-layout>