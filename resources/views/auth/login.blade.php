<x-guest-layout>
    <div class="flex justify-center mt-8">
        <!-- Logo -->
        <div class="text-center">
            <img src="{{ asset('images/pustik.jpg') }}" alt="Pustik Logo" class="mx-auto w-24 h-24 mb-6">
            <h1 class="text-4xl font-extrabold text-blue-800 mb-4">Masuk ke Pustik Kemlu</h1>
        </div>
    </div>

    <form method="POST" action="{{ route('login') }}" class="bg-white p-8 rounded-lg shadow-lg max-w-sm mx-auto">
        @csrf

        <!-- Session Status -->
        <x-auth-session-status class="mb-4" :status="session('status')" />

        <!-- Email Address -->
        <div class="mb-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mb-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="block mt-1 w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <!-- Submit Button -->
        <div class="mt-6">
            <button type="submit"
                class="w-full py-3 px-4 text-white bg-blue-600 rounded-lg font-medium shadow-md 
                       hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 
                       focus:ring-offset-2 transition duration-200">
                Masuk
            </button>
        </div>

        <!-- Registration Link -->
        <div class="text-center mt-4">
            <p class="text-sm text-gray-600">Belum Punya Akun? 
                <a href="{{ route('register') }}" class="text-blue-800 font-semibold hover:underline">Daftar Sekarang</a>
            </p>
        </div>
    </form>
</x-guest-layout>
