<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'SIM Kinerja') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

    <!-- Scripts & Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- Script Anti-FOUC untuk Dark Mode -->
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>

<body class="font-sans antialiased bg-theme-body text-theme-text transition-colors duration-300">
    
    <!-- x-data di sini menghubungkan tombol Hamburger di Navbar dengan Sidebar -->
    <div x-data="{ sidebarOpen: false }" class="flex h-screen overflow-hidden">
        
        <!-- 1. MEMANGGIL SIDEBAR (Livewire Volt Component) -->
        <livewire:layout.sidebar />

        <!-- Area Kanan (Navbar + Konten) -->
        <div class="relative flex flex-col flex-1 overflow-y-auto overflow-x-hidden">
            
            <!-- 2. MEMANGGIL NAVBAR (Livewire Volt Component) -->
            <livewire:layout.navigation />

            <!-- 3. TEMPAT KONTEN HALAMAN MUNCUL -->
            <!-- $slot adalah tempat di mana halaman Dasbor/Logbook Anda akan disuntikkan -->
            <main class="p-4 md:p-6 lg:p-8">
                {{ $slot }}
            </main>

        </div>
    </div>

    @stack('scripts')
</body>
</html>