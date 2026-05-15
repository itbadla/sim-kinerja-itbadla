<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use App\Models\Unit;
use App\Models\Periode;
use App\Models\WorkProgram;
use App\Models\FundSubmission;
use App\Models\FundDisbursement;
use App\Models\Logbook;
use Carbon\Carbon;

new #[Layout('layouts.app')] class extends Component {
    public function with(): array
    {
        // 1. Ambil data user yang sedang login beserta relasinya
        $user = Auth::user()->load([
            'units.kepalaUnit', 
            'units.parent.kepalaUnit', 
            'roles'
        ]);
        
        // 2. Tentukan Unit Utama (Ambil dari relasi pertama jika accessor unit tidak ada)
        $primaryUnit = $user->units->first() ?? null; 
        
        // Cek unit apa saja yang dipimpin oleh user ini
        $headedUnits = Unit::where('kepala_unit_id', $user->id)->get();

        // 3. Logika Penentuan Atasan Langsung
        $atasan = null;
        if ($primaryUnit) {
            if ($primaryUnit->kepala_unit_id === $user->id) {
                // Jika dia adalah Kepala Unit, maka atasannya adalah Kepala dari Unit Induknya
                $atasan = $primaryUnit->parent ? $primaryUnit->parent->kepalaUnit : null;
            } else {
                // Jika dia staff biasa, atasannya adalah Kepala Unit tempatnya bernaung
                $atasan = $primaryUnit->kepalaUnit;
            }
        }

        // 4. Ambil Jabatan dari tabel pivot untuk Unit Utama
        $jabatan = ($primaryUnit && $primaryUnit->pivot) ? $primaryUnit->pivot->jabatan_di_unit : 'Staff / Karyawan';

        // ==========================================
        // DATA METRIK MONITORING (PROKER & KEUANGAN)
        // ==========================================
        
        // 5. Dapatkan Periode Aktif
        $periodeAktif = Periode::where('is_current', true)->first();
        $periodeId = $periodeAktif ? $periodeAktif->id : null;

        // Inisialisasi variabel metrik dengan nilai default 0 agar tidak error di Blade
        $statistikProker = ['draft' => 0, 'review' => 0, 'disetujui' => 0];
        $keuangan = [
            'anggaran_direncanakan' => 0,
            'dana_disetujui' => 0,
            'dana_cair' => 0,
            'sisa_anggaran' => 0,
            'persentase_serapan' => 0
        ];
        $lpjPending = 0;

        // Jika user memiliki unit dan ada periode aktif, lakukan kalkulasi
        if ($primaryUnit && $periodeId) {
            // A. Statistik Program Kerja Unit
            $prokers = WorkProgram::where('unit_id', $primaryUnit->id)
                                  ->where('periode_id', $periodeId)
                                  ->get();
            
            $statistikProker['draft'] = $prokers->where('status', 'draft')->count();
            $statistikProker['review'] = $prokers->where('status', 'review_lpm')->count();
            $statistikProker['disetujui'] = $prokers->where('status', 'disetujui')->count();

            // B. Total anggaran dari proker yang disetujui (Pagu Kasar Unit)
            $keuangan['anggaran_direncanakan'] = $prokers->where('status', 'disetujui')->sum('anggaran_rencana');

            // C. Statistik Keuangan (Pengajuan & Pencairan)
            $submissions = FundSubmission::where('unit_id', $primaryUnit->id)
                                         ->where('periode_id', $periodeId)
                                         ->get();
            
            $keuangan['dana_disetujui'] = $submissions->where('status_pengajuan', 'approved')->sum('nominal_disetujui');
            
            // Ambil total dana yang benar-benar cair dari tabel disbursements untuk unit ini
            if ($submissions->isNotEmpty()) {
                $keuangan['dana_cair'] = FundDisbursement::whereIn('fund_submission_id', $submissions->pluck('id'))
                                                         ->where('status_cair', 'cair')
                                                         ->sum('nominal_cair');
                
                // D. Alert/Peringatan LPJ (Pencairan yang LPJ-nya belum selesai)
                $lpjPending = FundDisbursement::whereIn('fund_submission_id', $submissions->pluck('id'))
                                              ->where('status_cair', 'cair')
                                              ->whereIn('status_lpj', ['belum', 'menunggu_verifikasi'])
                                              ->count();
            }
            
            $keuangan['sisa_anggaran'] = $keuangan['anggaran_direncanakan'] - $keuangan['dana_cair'];
            
            // Hindari Division by Zero
            if ($keuangan['anggaran_direncanakan'] > 0) {
                $keuangan['persentase_serapan'] = round(($keuangan['dana_cair'] / $keuangan['anggaran_direncanakan']) * 100, 1);
            }
        }

        // 6. Statistik Kinerja Individu (Logbook)
        $awalBulan = Carbon::now()->startOfMonth();
        $akhirBulan = Carbon::now()->endOfMonth();
        
        $logbookCount = Logbook::where('user_id', $user->id)
                               ->whereBetween('tanggal', [$awalBulan, $akhirBulan])
                               ->count();

        $logbookApproved = Logbook::where('user_id', $user->id)
                                  ->whereBetween('tanggal', [$awalBulan, $akhirBulan])
                                  ->where('status', 'approved')
                                  ->count();

        // SEMUA VARIABEL WAJIB DIKEMBALIKAN DI SINI
        return [
            'user' => $user,
            'primaryUnit' => $primaryUnit,
            'jabatan' => $jabatan,
            'headedUnits' => $headedUnits,
            'atasan' => $atasan,
            'periodeAktif' => $periodeAktif,
            'statistikProker' => $statistikProker,
            'keuangan' => $keuangan,
            'lpjPending' => $lpjPending,
            'logbookCount' => $logbookCount,
            'logbookApproved' => $logbookApproved,
        ];
    }
}; ?>

<div class="space-y-6 pb-10">
    <!-- Header Dasbor & Peringatan Periode -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-theme-text tracking-tight">Dasbor Monitoring</h1>
            <p class="text-sm text-theme-muted mt-1">Pantau kinerja, program kerja, dan serapan anggaran secara real-time.</p>
        </div>
        <!-- Safety check menggunakan ?? null -->
        @if($periodeAktif ?? null)
            <div class="px-4 py-2 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center gap-2">
                <span class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse"></span>
                <span class="text-xs font-bold text-emerald-700">Periode Aktif: {{ $periodeAktif->nama_periode }}</span>
            </div>
        @else
            <div class="px-4 py-2 bg-rose-50 border border-rose-200 rounded-xl flex items-center gap-2">
                <span class="w-2.5 h-2.5 bg-rose-500 rounded-full"></span>
                <span class="text-xs font-bold text-rose-700">Belum ada periode yang diset aktif.</span>
            </div>
        @endif
    </div>

    <!-- Peringatan Sistem (LPJ Belum Selesai) -->
    @if(($lpjPending ?? 0) > 0)
    <div class="bg-rose-50 border-l-4 border-rose-500 p-4 rounded-r-xl shadow-sm flex items-start gap-4">
        <svg class="w-6 h-6 text-rose-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        <div>
            <h3 class="text-rose-800 font-bold text-sm">Tindakan Diperlukan: Laporan Pertanggungjawaban (LPJ)</h3>
            <p class="text-rose-600 text-xs mt-1">Terdapat <strong>{{ $lpjPending }} pencairan dana</strong> yang LPJ-nya belum selesai atau masih menunggu verifikasi. Pengajuan dana baru mungkin akan ditahan hingga LPJ ini diselesaikan.</p>
        </div>
    </div>
    @endif

    <!-- BARIS 1: PROFIL & RINGKASAN KEUANGAN UNIT (Grid) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- KOLOM 1: Profil Pengguna (Lebih Ringkas) -->
        <div class="bg-theme-surface p-6 rounded-3xl border border-theme-border shadow-sm flex flex-col items-center text-center relative overflow-hidden">
            <div class="w-20 h-20 rounded-2xl bg-theme-body border-2 border-theme-border flex items-center justify-center text-2xl font-extrabold text-primary shadow-inner mb-4">
                {{ strtoupper(substr($user->name ?? 'U', 0, 2)) }}
            </div>
            <h2 class="text-lg font-extrabold text-theme-text">{{ $user->name ?? 'Pengguna' }}</h2>
            <p class="text-xs text-theme-muted mb-3">{{ $primaryUnit->nama_unit ?? 'Belum terhubung ke Unit manapun' }}</p>
            <div class="inline-block px-3 py-1 bg-primary/10 border border-primary/20 text-primary rounded-lg text-[10px] font-bold uppercase tracking-widest mb-4">
                {{ $jabatan ?? 'Staff' }}
            </div>
            
            <div class="w-full text-left bg-theme-body p-3 rounded-xl border border-theme-border mt-auto">
                <p class="text-[10px] text-theme-muted font-bold uppercase">Atasan (Verifikator):</p>
                <p class="text-sm font-bold text-theme-text truncate">{{ $atasan->name ?? 'Pimpinan Tertinggi' }}</p>
            </div>
        </div>

        <!-- KOLOM 2 & 3: Metrik Keuangan Unit (Serapan Anggaran) -->
        <div class="lg:col-span-2 bg-theme-surface p-6 rounded-3xl border border-theme-border shadow-sm flex flex-col justify-between">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-sm font-bold text-theme-text uppercase tracking-wider flex items-center gap-2">
                    <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Serapan Anggaran Unit ({{ $periodeAktif->nama_periode ?? 'Belum ada periode' }})
                </h3>
            </div>

            <!-- Progress Bar Serapan -->
            <div class="mb-6">
                <div class="flex justify-between text-sm mb-2">
                    <span class="font-bold text-theme-text">Tingkat Serapan</span>
                    <span class="font-extrabold text-primary">{{ $keuangan['persentase_serapan'] ?? 0 }}%</span>
                </div>
                <div class="w-full bg-theme-body rounded-full h-3 border border-theme-border overflow-hidden">
                    <div class="bg-primary h-3 rounded-full transition-all duration-500" style="width: {{ min($keuangan['persentase_serapan'] ?? 0, 100) }}%"></div>
                </div>
            </div>

            <!-- Detail Angka -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="p-4 bg-theme-body rounded-2xl border border-theme-border">
                    <p class="text-[10px] text-theme-muted font-bold uppercase mb-1">Rencana (Pagu Proker)</p>
                    <p class="text-lg font-extrabold text-theme-text">Rp {{ number_format($keuangan['anggaran_direncanakan'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div class="p-4 bg-emerald-50 border border-emerald-100 rounded-2xl dark:bg-emerald-500/10 dark:border-emerald-500/20">
                    <p class="text-[10px] text-emerald-600 font-bold uppercase mb-1">Terealisasi (Cair)</p>
                    <p class="text-lg font-extrabold text-emerald-700 dark:text-emerald-400">Rp {{ number_format($keuangan['dana_cair'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div class="p-4 bg-theme-body rounded-2xl border border-theme-border">
                    <p class="text-[10px] text-theme-muted font-bold uppercase mb-1">Sisa Anggaran</p>
                    <p class="text-lg font-extrabold text-theme-text">Rp {{ number_format($keuangan['sisa_anggaran'] ?? 0, 0, ',', '.') }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- BARIS 2: STATUS PROGRAM KERJA & KINERJA INDIVIDU -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <!-- WIDGET: Status Program Kerja -->
        <div class="bg-theme-surface p-6 rounded-3xl border border-theme-border shadow-sm">
            <h3 class="text-sm font-bold text-theme-text uppercase tracking-wider mb-5 flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                Status Program Kerja Unit
            </h3>
            <div class="space-y-4">
                <div class="flex items-center justify-between p-3 bg-theme-body rounded-xl border border-theme-border">
                    <div class="flex items-center gap-3">
                        <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                        <span class="text-sm font-semibold text-theme-text">Proker Disetujui</span>
                    </div>
                    <span class="text-lg font-extrabold text-theme-text">{{ $statistikProker['disetujui'] ?? 0 }}</span>
                </div>
                <div class="flex items-center justify-between p-3 bg-theme-body rounded-xl border border-theme-border">
                    <div class="flex items-center gap-3">
                        <div class="w-2 h-2 rounded-full bg-amber-500"></div>
                        <span class="text-sm font-semibold text-theme-text">Direviu LPM / Pimpinan</span>
                    </div>
                    <span class="text-lg font-extrabold text-theme-text">{{ $statistikProker['review'] ?? 0 }}</span>
                </div>
                <div class="flex items-center justify-between p-3 bg-theme-body rounded-xl border border-theme-border">
                    <div class="flex items-center gap-3">
                        <div class="w-2 h-2 rounded-full bg-gray-400"></div>
                        <span class="text-sm font-semibold text-theme-text">Masih Draft</span>
                    </div>
                    <span class="text-lg font-extrabold text-theme-text">{{ $statistikProker['draft'] ?? 0 }}</span>
                </div>
            </div>
        </div>

        <!-- WIDGET: Kinerja Individu (Logbook) -->
        <div class="bg-theme-surface p-6 rounded-3xl border border-theme-border shadow-sm flex flex-col">
            <h3 class="text-sm font-bold text-theme-text uppercase tracking-wider mb-5 flex items-center gap-2">
                <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Logbook & Kinerja Anda (Bulan Ini)
            </h3>
            
            <div class="flex-1 flex items-center justify-center gap-8">
                <div class="text-center">
                    <p class="text-4xl font-extrabold text-theme-text">{{ $logbookCount ?? 0 }}</p>
                    <p class="text-[10px] text-theme-muted font-bold uppercase mt-2">Total Diinput</p>
                </div>
                <div class="h-12 w-px bg-theme-border"></div>
                <div class="text-center">
                    <p class="text-4xl font-extrabold text-emerald-600">{{ $logbookApproved ?? 0 }}</p>
                    <p class="text-[10px] text-theme-muted font-bold uppercase mt-2">Disetujui Atasan</p>
                </div>
            </div>

            <!-- Tombol Aksi Cepat -->
            <div class="mt-6 grid grid-cols-2 gap-3">
                <a href="#" class="flex items-center justify-center gap-2 bg-primary text-white py-2.5 rounded-xl text-xs font-bold hover:bg-primary/90 transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Isi Logbook
                </a>
                <a href="#" class="flex items-center justify-center gap-2 bg-theme-body border border-theme-border text-theme-text py-2.5 rounded-xl text-xs font-bold hover:bg-theme-surface transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Pengajuan Dana
                </a>
            </div>
        </div>
    </div>
</div>