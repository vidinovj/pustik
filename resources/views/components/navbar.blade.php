<nav class="bg-black text-black" x-data="{ isOpen: false }">
    <div class="mx-auto max-w-7xl px-15 sm:px-20 lg:px-15">
        <div class="flex h-22 items-center justify-between">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <img class="h-15 w-1" src="{{ asset('img/logo.png') }}" alt="Your Company">
                </div>
                <div class="hidden md:block">
                    <div class="ml-10 flex items-baseline space-x-8">
                        <x-nav-link href='/' class="bg-black text-black px-5 py-2 rounded-md">
                            Beranda
                        </x-nav-link>
                        <x-nav-link href='/ktbk' class="bg-black text-black px-5 py-2 rounded-md">
                            Kebijakan TIK by Kemlu
                        </x-nav-link>
                        <x-nav-link href='/ktbnk' class="bg-black text-black px-5 py-2 rounded-md">
                            Kebijakan TIK by Non Kemlu
                        </x-nav-link>
                        <x-nav-link href='/nkmdp' class="bg-black text-black px-5 py-2 rounded-md">
                            Nota Kesepahaman (MoU) dan PKS
                        </x-nav-link>

                        @auth
                            <!-- Tombol Logout jika sudah login -->
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="bg-black text-black px-3 py-2 rounded-md">
                                    Logout
                                </button>
                            </form>
                        @else
                            <!-- Tombol Login jika belum login -->
                            <x-nav-link href="{{ route('login') }}" class="bg-black text-black px-3 py-2 rounded-md">
                                Login
                            </x-nav-link>
                        @endauth
                    </div>
                </div>
            </div>
            <div class="-mr-2 flex md:hidden">
                <!-- Mobile menu button -->
                <button type="button" @click="isOpen = !isOpen"
                    class="relative inline-flex items-center justify-center rounded-md bg-black p-2 text-black hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500">
                    <span class="sr-only">Open main menu</span>
                    <svg :class="{'hidden': isOpen, 'block': !isOpen }" class="block h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                    <svg :class="{'block': isOpen, 'hidden': !isOpen }" class="hidden h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile menu -->
    <div x-show="isOpen" class="md:hidden" id="mobile-menu">
        <div class="space-y-1 px-2 pb-3 pt-2 sm:px-3">
            <x-nav-link href="/" class="bg-black text-black px-3 py-2 rounded-md">
                Beranda
            </x-nav-link>
            <x-nav-link href="/ktbk" class="bg-black text-black px-3 py-2 rounded-md">
                Kebijakan TIK by Kemlu
            </x-nav-link>
            <x-nav-link href="/ktbnk" class="bg-black text-black px-3 py-2 rounded-md">
                Kebijakan TIK by Non Kemlu
            </x-nav-link>
            <x-nav-link href="/nkmdp" class="bg-black text-black px-3 py-2 rounded-md">
                Nota Kesepahaman (MoU) dan PKS
            </x-nav-link>
        </div>
        <div class="border-t border-gray-300 pb-3 pt-4 px-2">
            @auth
                <!-- Tombol Logout di menu mobile -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block bg-black text-black px-3 py-2 rounded-md text-base font-medium">
                        Logout
                    </button>
                </form>
            @else
                <!-- Tombol Login di menu mobile -->
                <x-nav-link href="{{ route('login') }}" class="bg-black text-black px-3 py-2 rounded-md">
                    Login
                </x-nav-link>
            @endauth
        </div>
    </div>
</nav>
