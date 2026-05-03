<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIM Kinerja - ITBADLA</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet" />
    
    <!-- Scripts & Tailwind -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Custom Animation untuk Ilustrasi Baru -->
    <style>
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
            100% { transform: translateY(0px); }
        }
        @keyframes float-delayed {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        .animate-float { animation: float 6s ease-in-out infinite; }
        .animate-float-delayed { animation: float-delayed 7s ease-in-out infinite 2s; }
    </style>
</head>
<body class="bg-white text-slate-800 font-['Inter'] antialiased selection:bg-emerald-500 selection:text-white flex flex-col min-h-screen">

    <!-- x-data untuk Sidebar Mobile dan Efek Scroll Navbar -->
    <div x-data="{ mobileMenuOpen: false, scrolled: false }" @scroll.window="scrolled = (window.pageYOffset > 20)">
        
        <!-- Navbar Bersih & Proporsional (Dengan Efek Transisi Transparan) -->
        <nav :class="scrolled ? 'bg-white/90 backdrop-blur-md border-gray-100 shadow-sm' : 'bg-transparent border-transparent'"
             class="fixed w-full top-0 z-50 border-b h-20 flex items-center transition-all duration-300">
            <div class="max-w-7xl mx-auto w-full flex justify-between items-center px-6 lg:px-8">
                
                <!-- Logo ITBADLA -->
                <div class="flex items-center gap-3 cursor-pointer">
                    <div class="w-10 h-10 rounded-xl bg-emerald-600 flex items-center justify-center text-white font-extrabold text-xl shadow-md shadow-emerald-100">
                        K
                    </div>
                    <div class="flex flex-col justify-center">
                        <span class="font-bold text-lg tracking-tight text-slate-900 leading-none mb-1">SIM Kinerja</span>
                        <span class="text-[10px] text-emerald-600 font-bold tracking-widest uppercase leading-none">ITBADLA</span>
                    </div>
                </div>
                
                <!-- Navigasi Desktop -->
                <div class="hidden md:flex items-center gap-8">

                    @if (Route::has('login'))
                        @auth
                            <a href="{{ url('/dashboard') }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700 transition-colors">Masuk Dasbor &rarr;</a>
                        @else
                            <a href="{{ route('login') }}" class="text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-6 py-2.5 rounded-lg shadow-sm transition-all hover:-translate-y-0.5">Log In</a>
                        @endauth
                    @endif
                </div>

                <!-- Tombol Hamburger Mobile -->
                <div class="md:hidden flex items-center">
                    <button @click="mobileMenuOpen = true" type="button" class="text-slate-600 hover:text-emerald-600 p-2">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                </div>
            </div>
        </nav>

        <!-- Sidebar Mobile -->
        <div x-show="mobileMenuOpen" 
             x-transition.opacity.duration.300ms
             class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 md:hidden" 
             @click="mobileMenuOpen = false" style="display: none;"></div>

        <div x-show="mobileMenuOpen" 
             x-transition:enter="transition ease-in-out duration-300 transform"
             x-transition:enter-start="translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in-out duration-300 transform"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="translate-x-full"
             class="fixed inset-y-0 right-0 w-64 bg-white shadow-2xl z-50 md:hidden flex flex-col" style="display: none;">
            
            <div class="p-6 flex flex-col h-full">
                <div class="flex justify-between items-center mb-8 border-b border-gray-100 pb-4">
                    <span class="font-bold text-lg text-slate-900">Menu</span>
                    <button @click="mobileMenuOpen = false" class="text-slate-400 hover:text-rose-500 bg-gray-50 rounded-full p-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="flex flex-col gap-4 flex-grow">
                    @if (Route::has('login'))
                        @auth
                            <a href="{{ url('/dashboard') }}" class="text-emerald-600 font-bold bg-emerald-50 p-3 rounded-lg text-center mt-2">Masuk Dasbor</a>
                        @else
                            <a href="{{ route('login') }}" class="mt-2 text-white font-semibold bg-emerald-600 p-3 rounded-xl text-center shadow-md shadow-emerald-200">Log In</a>
                        @endauth
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Hero Section: Diperbaiki agar tidak terpotong navbar -->
    <main class="relative flex-grow flex flex-col justify-center w-full min-h-screen pt-32 pb-20 lg:pt-40 lg:pb-32 overflow-hidden bg-slate-50/50">
        
        <!-- Aksen grid tipis di background agar tidak terlalu polos -->
        <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHBhdGggZD0iTTEgMWgydjJIMUMxeiIgZmlsbD0iI2U1ZTdlYiIgZmlsbC1ydWxlPSJldmVub2RkIi8+PC9zdmc+')] opacity-40 pointer-events-none"></div>

        <div class="max-w-7xl mx-auto px-6 lg:px-8 w-full grid lg:grid-cols-2 gap-12 lg:gap-8 items-center relative z-10">
            
            <!-- Tipografi Simpel & Langsung (Kiri) -->
            <div class="space-y-8 text-center lg:text-left max-w-2xl mx-auto lg:mx-0">
                
                
                <!-- Headline Simpel ITBADLA -->
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold leading-[1.15] tracking-tight text-slate-900">
                    SIM Kinerja <br>
                    <span class="text-emerald-600">ITBADLA</span>
                </h1>
                
                <!-- Deskripsi Ringkas -->
                <p class="text-base sm:text-lg text-slate-600 leading-relaxed font-normal">
                    Sistem integrasi kinerja Dosen dan Tenaga Kependidikan  <b>Institut Teknologi dan Bisnis Ahmad Dahlan Lamongan</b>.
                </p>
                
                <!-- CTA -->
                <div class="flex flex-col sm:flex-row gap-4 pt-4 justify-center lg:justify-start">
                    <a href="{{ route('login') }}" class="inline-flex justify-center items-center px-8 py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-semibold shadow-lg shadow-emerald-100 transition-all duration-300 hover:-translate-y-0.5">
                        Log In Pengguna
                        <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </a>
                </div>
            </div>

            <!-- Ilustrasi Abstrak Bersih pengganti Mockup (Kanan) -->
            <div class="relative hidden lg:flex justify-center items-center h-full">
                <!-- Lingkaran Latar Belakang -->
                <div class="absolute w-[400px] h-[400px] bg-emerald-50 rounded-full blur-3xl opacity-60"></div>
                
                <!-- Komposisi Kartu Abstrak (Lebih bersih dari Mockup Dashboard) -->
                <div class="relative w-full max-w-md aspect-square flex items-center justify-center">
                    
                    <!-- Kartu Tengah (Utama) -->
                    <div class="relative z-10 bg-white p-8 rounded-[2rem] shadow-2xl shadow-slate-200/60 border border-slate-100 flex flex-col items-center text-center animate-float w-64">
                        <div class="w-20 h-20 bg-emerald-50 rounded-2xl flex items-center justify-center mb-5 text-emerald-600">
                            <!-- Icon Chart / Target -->
                            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-slate-800">Kinerja Optimal</h3>
                        <div class="h-1 w-10 bg-emerald-500 rounded-full mt-3 mb-2"></div>
                        <p class="text-slate-400 text-xs font-medium">Terintegrasi Sistem</p>
                    </div>

                    <!-- Kartu Melayang 1 (Kanan Atas) -->
                    <div class="absolute -right-4 top-12 z-20 bg-white p-4 rounded-2xl shadow-xl shadow-slate-200/50 border border-slate-50 flex items-center gap-4 animate-float-delayed">
                        <div class="w-12 h-12 bg-amber-50 rounded-full flex items-center justify-center text-amber-500">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <div class="text-left pr-2">
                            <p class="text-sm font-bold text-slate-800">Logbook</p>
                            <p class="text-[11px] text-slate-500">Tercatat Rapi</p>
                        </div>
                    </div>

                    <!-- Kartu Melayang 2 (Kiri Bawah) -->
                    <div class="absolute -left-8 bottom-16 z-20 bg-white p-4 rounded-2xl shadow-xl shadow-slate-200/50 border border-slate-50 flex items-center gap-4 animate-float" style="animation-delay: 1.5s;">
                        <div class="w-12 h-12 bg-blue-50 rounded-full flex items-center justify-center text-blue-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                        </div>
                        <div class="text-left pr-2">
                            <p class="text-sm font-bold text-slate-800">Tri Dharma</p>
                            <p class="text-[11px] text-slate-500">Tersinkronisasi</p>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-100 bg-slate-50 py-10 mt-auto">
        <div class="max-w-7xl mx-auto px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center gap-4 text-center md:text-left">
            <div class="text-sm text-slate-500 font-medium">
                &copy; {{ date('Y') }} Institut Teknologi dan Bisnis Ahmad Dahlan Lamongan.
            </div>
            <div class="flex justify-center gap-6 text-sm text-slate-400">
                <a href="#" class="hover:text-emerald-600 transition-colors">Panduan</a>
                <a href="#" class="hover:text-emerald-600 transition-colors">Helpdesk IT</a>
            </div>
        </div>
    </footer>

</body>
</html>