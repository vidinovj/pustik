<!-- resources/views/layouts/admin.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-white shadow-lg">
            <div class="p-4">
                <h2 class="text-2xl font-semibold text-gray-800">Admin Panel</h2>
            </div>
            <nav class="mt-4">
                <a href="{{ route('admin.dashboard') }}" 
                   class="block px-4 py-2 text-gray-600 hover:bg-blue-500 hover:text-white {{ request()->routeIs('admin.dashboard') ? 'bg-blue-500 text-white' : '' }}">
                    Dashboard
                </a>
                <a href="{{ route('admin.kebijakan-kemlu.index') }}" 
                   class="block px-4 py-2 text-gray-600 hover:bg-blue-500 hover:text-white {{ request()->routeIs('admin.kebijakan-kemlu.*') ? 'bg-blue-500 text-white' : '' }}">
                    Kebijakan TIK Kemlu
                </a>
                <a href="{{ route('admin.kebijakan-non-kemlu.index') }}" 
                   class="block px-4 py-2 text-gray-600 hover:bg-blue-500 hover:text-white {{ request()->routeIs('admin.kebijakan-non-kemlu.*') ? 'bg-blue-500 text-white' : '' }}">
                    Kebijakan TIK Non-Kemlu
                </a>
                <a href="{{ route('admin.nota-kesepahaman.index') }}" 
                   class="block px-4 py-2 text-gray-600 hover:bg-blue-500 hover:text-white {{ request()->routeIs('admin.nota-kesepahaman.*') ? 'bg-blue-500 text-white' : '' }}">
                    Nota Kesepahaman
                </a>
                <form method="POST" action="{{ route('logout') }}" class="block px-4 py-2">
                    @csrf
                    <button type="submit" class="text-gray-600 hover:text-red-500">
                        Logout
                    </button>
                </form>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1">
            <!-- Top Navigation -->
            <header class="bg-white shadow">
                <div class="px-4 py-6">
                    <h2 class="text-xl font-semibold text-gray-800">
                        @yield('header', 'Dashboard')
                    </h2>
                </div>
            </header>

            <!-- Page Content -->
            <main class="p-6">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>