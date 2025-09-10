{{-- resources/views/components/navbar.blade.php --}}
<nav class="navbar navbar-expand-lg navbar-dark bg-dark navbar-custom" x-data="{ isOpen: false }">
    <div class="container-fluid px-4">
        <!-- Logo -->
        <a class="navbar-brand d-flex align-items-center" href="/">
            <img src="{{ asset('img/logo.png') }}" alt="Logo" height="40" class="me-2">
        </a>

        <!-- Mobile Toggle Button -->
        <button class="navbar-toggler" type="button" @click="isOpen = !isOpen" 
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Navigation Links -->
        <div class="collapse navbar-collapse" :class="{'show': isOpen}" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link {{ request()->is('/') ? 'active' : '' }}" href="/">
                        Beranda
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->is('ktbk*') ? 'active' : '' }}" href="/ktbk">
                        Kebijakan Internal
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->is('ktbnk*') ? 'active' : '' }}" href="/ktbnk">
                        Kebijakan Eksternal
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->is('nkmdp*') ? 'active' : '' }}" href="/nkmdp">
                        Pusdatin
                    </a>
                </li>
            </ul>

            <!-- Auth Section -->
            <ul class="navbar-nav">
                @auth
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            {{ Auth::user()->name ?? 'User' }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <form method="POST" action="{{ route('logout') }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="dropdown-item">
                                        Logout
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                @else
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('login') }}">
                            Login
                        </a>
                    </li>
                @endauth
            </ul>
        </div>
    </div>
</nav>