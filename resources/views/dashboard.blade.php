<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:justify-between md:items-end gap-4">
            <div>
                <!-- Sub-heading kecil -->
                <p class="text-theme-muted text-sm font-medium uppercase tracking-widest mb-1">Portal Dosen & Tendik</p>
                <!-- Heading Utama (Besar dan Bersih) -->
                <h2 class="font-extrabold text-3xl text-theme-text tracking-tight leading-none">
                    Dasbor Kinerja
                </h2>
            </div>
            
            <!-- Indikator Status & Tombol Aksi Cepat -->
            <div class="flex items-center gap-4">
                <span class="inline-flex items-center gap-2 py-1.5 px-3 rounded-md text-xs font-semibold bg-surface border border-surface-border text-theme-text shadow-sm">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-primary"></span>
                    </span>
                    Sistem Aktif
                </span>
                
                <button class="bg-primary hover:bg-primary-hover text-theme-inverse text-sm font-semibold py-2 px-4 rounded-lg shadow-sm shadow-primary/20 transition-all duration-200">
                    + Buat Laporan
                </button>
            </div>
        </div>
    </x-slot>

    <!-- Kontainer Utama: Latar belakang abu-abu sangat terang agar kartu putih menonjol -->
    <div class="py-10 bg-theme-body min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            
            <!-- Ucapan Selamat Datang (Clean Text) -->
            <div class="bg-surface rounded-2xl p-8 border border-surface-border shadow-sm">
                <h3 class="text-xl font-bold text-theme-text mb-2">Selamat datang kembali, {{ Auth::user()->name }}.</h3>
                <p class="text-theme-muted text-sm max-w-3xl leading-relaxed">
                    Ringkasan aktivitas Tri Dharma dan Logbook Harian Anda bulan ini. Pastikan untuk memperbarui logbook Anda secara berkala untuk menjaga akuntabilitas kinerja.
                </p>
            </div>

            <!-- Kartu Statistik (Minimalis) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                
                <!-- Stat 1 -->
                <div class="bg-surface rounded-2xl p-6 border border-surface-border shadow-sm group hover:border-primary/30 transition-colors">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-10 h-10 rounded-lg bg-primary/10 text-primary flex items-center justify-center group-hover:scale-110 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        </div>
                        <span class="text-xs font-bold text-primary bg-primary/10 px-2 py-1 rounded-md">+2 Hari ini</span>
                    </div>
                    <div>
                        <h4 class="text-3xl font-extrabold text-theme-text">12</h4>
                        <p class="text-sm font-medium text-theme-muted mt-1">Entri Logbook Bulan Ini</p>
                    </div>
                </div>

                <!-- Stat 2 -->
                <div class="bg-surface rounded-2xl p-6 border border-surface-border shadow-sm group hover:border-primary/30 transition-colors">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-10 h-10 rounded-lg bg-blue-500/10 text-blue-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                        </div>
                        <span class="text-xs font-bold text-theme-muted bg-theme-body px-2 py-1 rounded-md border border-surface-border">SIAKAD</span>
                    </div>
                    <div>
                        <h4 class="text-3xl font-extrabold text-theme-text">Sinkron</h4>
                        <p class="text-sm font-medium text-theme-muted mt-1">Status Data Tri Dharma</p>
                    </div>
                </div>

                <!-- Stat 3 -->
                <div class="bg-surface rounded-2xl p-6 border border-surface-border shadow-sm group hover:border-accent/30 transition-colors">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-10 h-10 rounded-lg bg-accent/10 text-accent flex items-center justify-center group-hover:scale-110 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                    </div>
                    <div>
                        <h4 class="text-3xl font-extrabold text-theme-text">2</h4>
                        <p class="text-sm font-medium text-theme-muted mt-1">Tugas / Laporan Tertunda</p>
                    </div>
                </div>

            </div>

            <!-- Section Bawah: Tabel Clean & Aktivitas -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Area Kiri (Lebih Lebar): Contoh Tabel Clean -->
                <div class="lg:col-span-2 bg-surface rounded-2xl border border-surface-border shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-surface-border flex justify-between items-center bg-surface">
                        <h3 class="font-bold text-lg text-theme-text">Logbook Terbaru</h3>
                        <a href="#" class="text-sm font-semibold text-primary hover:text-primary-hover">Lihat Semua &rarr;</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-theme-muted uppercase bg-theme-body border-b border-surface-border">
                                <tr>
                                    <th scope="col" class="px-6 py-4 font-semibold">Tanggal</th>
                                    <th scope="col" class="px-6 py-4 font-semibold">Aktivitas</th>
                                    <th scope="col" class="px-6 py-4 font-semibold">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-border">
                                <!-- Baris 1 -->
                                <tr class="hover:bg-theme-body/50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-theme-text font-medium">Hari ini</td>
                                    <td class="px-6 py-4 text-theme-muted">Penyusunan modul ajar Pemrograman Web</td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-md text-xs font-medium bg-primary/10 text-primary border border-primary/20">
                                            Selesai
                                        </span>
                                    </td>
                                </tr>
                                <!-- Baris 2 -->
                                <tr class="hover:bg-theme-body/50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-theme-text font-medium">02 Mei 2026</td>
                                    <td class="px-6 py-4 text-theme-muted">Rapat koordinasi prodi Sistem Informasi</td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-md text-xs font-medium bg-primary/10 text-primary border border-primary/20">
                                            Selesai
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Area Kanan (Lebih Kecil): Timeline / Aktivitas -->
                <div class="bg-surface rounded-2xl border border-surface-border shadow-sm p-6">
                    <h3 class="font-bold text-lg text-theme-text mb-6">Linimasa</h3>
                    
                    <div class="relative border-l-2 border-surface-border ml-3 space-y-8">
                        <!-- Item Timeline 1 -->
                        <div class="relative">
                            <div class="absolute -left-[21px] bg-surface p-1 rounded-full">
                                <div class="w-3 h-3 bg-primary rounded-full ring-4 ring-primary/20"></div>
                            </div>
                            <div class="pl-6">
                                <p class="text-sm font-semibold text-theme-text">Login Sistem</p>
                                <p class="text-xs text-theme-muted mt-1">Baru saja</p>
                            </div>
                        </div>

                        <!-- Item Timeline 2 -->
                        <div class="relative">
                            <div class="absolute -left-[19px] bg-surface p-1 rounded-full">
                                <div class="w-2 h-2 bg-theme-muted rounded-full"></div>
                            </div>
                            <div class="pl-6">
                                <p class="text-sm font-medium text-theme-text">Sinkronisasi SIAKAD</p>
                                <p class="text-xs text-theme-muted mt-1">Kemarin, 14:00 WIB</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>