<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\FundSubmission;
use App\Models\Unit;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')] class extends Component {
    
    // Filter State
    public $filterBulan = '';
    public $filterTahun = '';
    public $filterUnit = '';

    public function mount()
    {
        // Default filter ke bulan dan tahun saat ini
        $this->filterBulan = date('m');
        $this->filterTahun = date('Y');
    }

    public function with(): array
    {
        $user = Auth::user();
        
        // Base Query: Hanya ambil yang sudah CAIR (Approved)
        $query = FundSubmission::with(['unit', 'user'])->where('status', 'approved');

        // LOGIKA HAK AKSES MELIHAT LAPORAN
        $availableUnits = collect();
        if (!$user->hasRole(['admin', 'keuangan'])) {
            $headedUnitIds = Unit::where('kepala_unit_id', $user->id)->pluck('id');
            $query->whereIn('unit_id', $headedUnitIds);
            $availableUnits = Unit::whereIn('id', $headedUnitIds)->get();
        } else {
            $availableUnits = Unit::all();
        }

        // Apply Filters
        if ($this->filterBulan) {
            $query->whereMonth('updated_at', $this->filterBulan);
        }
        if ($this->filterTahun) {
            $query->whereYear('updated_at', $this->filterTahun);
        }
        if ($this->filterUnit) {
            $query->where('unit_id', $this->filterUnit);
        }

        $submissions = $query->latest()->get();

        // ---------------------------------------------------------
        // KALKULASI METRIK DASHBOARD YANG DIPERBARUI
        // ---------------------------------------------------------
        
        // 1. Total Dana Cair
        $totalCair = $submissions->sum('nominal');
        
        // 2. Filter LPJ yang sudah Selesai & Pending
        $lpjSelesai = $submissions->where('status_lpj', 'selesai');
        $lpjPending = $submissions->where('status_lpj', '!=', 'selesai');

        // 3. Realisasi dari LPJ Selesai
        $totalRealisasi = $lpjSelesai->sum('nominal_realisasi');

        // 4. Hitung Rincian Pengembalian Dana (Hanya untuk yang sisa kembaliannya > 0)
        $totalSelisih = 0;
        $totalDikembalikan = 0;
        $totalMenunggakKembali = 0;

        foreach ($lpjSelesai as $item) {
            $selisihItem = $item->nominal - $item->nominal_realisasi;
            if ($selisihItem > 0) {
                $totalSelisih += $selisihItem;
                if ($item->waktu_pengembalian) {
                    $totalDikembalikan += $selisihItem; // Sudah lunas dikembalikan
                } else {
                    $totalMenunggakKembali += $selisihItem; // Masih dibawa pengaju
                }
            }
        }
        
        // 5. Hitung LPJ Menunggak/Pending
        $totalPendingLpj = $lpjPending->count();
        $nominalPendingLpj = $lpjPending->sum('nominal');

        return [
            'submissions' => $submissions,
            'totalCair' => $totalCair,
            'totalRealisasi' => $totalRealisasi,
            'totalSelisih' => $totalSelisih,
            'totalDikembalikan' => $totalDikembalikan,
            'totalMenunggakKembali' => $totalMenunggakKembali,
            'totalPendingLpj' => $totalPendingLpj,
            'nominalPendingLpj' => $nominalPendingLpj,
            'availableUnits' => $availableUnits,
        ];
    }
}; ?>

<div class="space-y-6 relative">
    <!-- Header Halaman -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-theme-text tracking-tight">Status Anggaran & Realisasi</h1>
            <p class="text-sm text-theme-muted mt-1">Pantau serapan anggaran, laporan LPJ, dan tracking pengembalian sisa dana operasional.</p>
        </div>
    </div>

    <!-- Kotak Filter -->
    <div class="bg-theme-surface p-4 rounded-2xl border border-theme-border shadow-sm flex flex-col md:flex-row items-center gap-3">
        <!-- Filter Bulan -->
        <div class="w-full md:w-48 flex-shrink-0">
            <select wire:model.live="filterBulan" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text font-medium">
                <option value="">Semua Bulan</option>
                @foreach(range(1, 12) as $m)
                    <option value="{{ sprintf('%02d', $m) }}">{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                @endforeach
            </select>
        </div>

        <!-- Filter Tahun -->
        <div class="w-full md:w-32 flex-shrink-0">
            <select wire:model.live="filterTahun" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text font-medium">
                <option value="">Semua Tahun</option>
                @for($y = date('Y'); $y >= 2023; $y--)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endfor
            </select>
        </div>

        <!-- Filter Unit -->
        <div class="w-full relative">
            <select wire:model.live="filterUnit" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text font-medium">
                <option value="">Semua Unit (Keseluruhan)</option>
                @foreach($availableUnits as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->nama_unit }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Dashboard Metrics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        
        <!-- Total Cair -->
        <div class="bg-theme-surface p-5 rounded-2xl border border-theme-border shadow-sm flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <p class="text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1">Total Dana Cair</p>
                <h3 class="text-xl font-extrabold text-theme-text">Rp {{ number_format($totalCair, 0, ',', '.') }}</h3>
                <p class="text-[10px] text-theme-muted mt-1">Total pengajuan yang disetujui</p>
            </div>
        </div>

        <!-- Total Realisasi -->
        <div class="bg-theme-surface p-5 rounded-2xl border border-theme-border shadow-sm flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <p class="text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1">Total Realisasi (Selesai)</p>
                <h3 class="text-xl font-extrabold text-theme-text">Rp {{ number_format($totalRealisasi, 0, ',', '.') }}</h3>
                <p class="text-[10px] text-theme-muted mt-1">Uang yang sudah dipertanggungjawabkan</p>
            </div>
        </div>

        <!-- Status Pengembalian Sisa Dana -->
        <div class="bg-theme-surface p-5 rounded-2xl border border-theme-border shadow-sm flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl {{ $totalMenunggakKembali > 0 ? 'bg-amber-50 text-amber-600 dark:bg-amber-500/10' : 'bg-slate-50 text-slate-600 dark:bg-slate-500/10' }} flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
            </div>
            <div class="w-full">
                <p class="text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1">Total Sisa Kembalian</p>
                <h3 class="text-xl font-extrabold text-theme-text">Rp {{ number_format($totalSelisih, 0, ',', '.') }}</h3>
                
                @if($totalSelisih > 0)
                    <div class="mt-2 w-full">
                        <div class="flex justify-between text-[9px] font-bold mb-1">
                            <span class="text-emerald-600">Lunas: Rp {{ number_format($totalDikembalikan, 0, ',', '.') }}</span>
                            @if($totalMenunggakKembali > 0)
                                <span class="text-red-500">Nunggak: Rp {{ number_format($totalMenunggakKembali, 0, ',', '.') }}</span>
                            @endif
                        </div>
                        <!-- Progress Bar Mini -->
                        <div class="w-full bg-theme-body rounded-full h-1.5 overflow-hidden flex">
                            <div class="bg-emerald-500 h-1.5" style="width: {{ ($totalDikembalikan / $totalSelisih) * 100 }}%"></div>
                            <div class="bg-red-500 h-1.5" style="width: {{ ($totalMenunggakKembali / $totalSelisih) * 100 }}%"></div>
                        </div>
                    </div>
                @else
                    <p class="text-[10px] text-theme-muted mt-1">Tidak ada sisa dana kembalian.</p>
                @endif
            </div>
        </div>

        <!-- Pending LPJ -->
        <div class="bg-theme-surface p-5 rounded-2xl border border-theme-border shadow-sm flex items-start gap-4 relative overflow-hidden">
            @if($totalPendingLpj > 0)
                <div class="absolute top-0 right-0 w-16 h-16 bg-red-500/10 rounded-bl-full -z-0"></div>
            @endif
            <div class="w-12 h-12 rounded-xl {{ $totalPendingLpj > 0 ? 'bg-red-50 text-red-500 dark:bg-red-500/10' : 'bg-theme-body text-theme-muted' }} flex items-center justify-center shrink-0 z-10">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div class="z-10">
                <p class="text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1">Menunggak LPJ</p>
                <h3 class="text-xl font-extrabold {{ $totalPendingLpj > 0 ? 'text-red-500' : 'text-theme-text' }}">{{ $totalPendingLpj }} Pengajuan</h3>
                <p class="text-[10px] text-theme-muted mt-1">Senilai Rp {{ number_format($nominalPendingLpj, 0, ',', '.') }}</p>
            </div>
        </div>
    </div>

    <!-- Tabel Rekapitulasi Rinci -->
    <div class="bg-theme-surface border border-theme-border rounded-2xl overflow-hidden shadow-sm">
        <div class="px-6 py-4 border-b border-theme-border bg-theme-body/50">
            <h3 class="text-sm font-bold text-theme-text">Rincian Realisasi & Pengembalian per Pengajuan</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[1000px]">
                <thead class="bg-theme-body/30 border-b border-theme-border">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest w-48">Unit / Pengaju</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest">Detail Keperluan</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest text-right">Dana Cair</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest text-right">Realisasi (LPJ)</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest text-right">Selisih Kembalian</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest text-center w-36">Status LPJ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-theme-border">
                    @forelse($submissions as $item)
                        @php
                            $realisasi = $item->status_lpj === 'selesai' ? $item->nominal_realisasi : 0;
                            $selisihItem = $item->nominal - $realisasi;
                        @endphp
                        <tr class="hover:bg-theme-body/30 transition-colors">
                            
                            <!-- Unit / Pengaju -->
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm font-bold text-theme-text">{{ $item->unit ? $item->unit->nama_unit : 'Pribadi' }}</div>
                                <div class="text-[10px] text-theme-muted mt-0.5">Oleh: {{ $item->user->name ?? '-' }}</div>
                            </td>
                            
                            <!-- Keperluan -->
                            <td class="px-6 py-4 align-top">
                                <p class="text-sm font-medium text-theme-text line-clamp-2" title="{{ $item->keperluan }}">{{ $item->keperluan }}</p>
                                <p class="text-[9px] text-theme-muted mt-1 uppercase">Cair: {{ $item->updated_at->format('d M Y') }}</p>
                            </td>

                            <!-- Dana Cair -->
                            <td class="px-6 py-4 align-top text-right text-sm font-bold text-theme-text">
                                Rp {{ number_format($item->nominal, 0, ',', '.') }}
                            </td>

                            <!-- Realisasi -->
                            <td class="px-6 py-4 align-top text-right">
                                @if($item->status_lpj === 'selesai')
                                    <div class="text-sm font-bold text-blue-600 dark:text-blue-400">Rp {{ number_format($realisasi, 0, ',', '.') }}</div>
                                @else
                                    <span class="text-sm italic text-theme-muted">-</span>
                                @endif
                            </td>

                            <!-- Selisih & Status Pengembalian -->
                            <td class="px-6 py-4 align-top text-right">
                                @if($item->status_lpj === 'selesai')
                                    @if($selisihItem > 0)
                                        <div class="text-sm font-bold text-amber-600 mb-1">+ Rp {{ number_format($selisihItem, 0, ',', '.') }}</div>
                                        @if($item->waktu_pengembalian)
                                            <span class="inline-flex items-center gap-1 text-[9px] font-bold text-emerald-600 bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 rounded uppercase">Lunas Kembali</span>
                                        @else
                                            <span class="inline-flex items-center gap-1 text-[9px] font-bold text-red-500 bg-red-50 border border-red-200 px-1.5 py-0.5 rounded uppercase animate-pulse">Menunggak</span>
                                        @endif
                                    @elseif($selisihItem < 0)
                                        <span class="inline-flex items-center gap-1 text-xs font-bold text-red-500">
                                            - Rp {{ number_format(abs($selisihItem), 0, ',', '.') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-xs font-bold text-emerald-600">Pas (0)</span>
                                    @endif
                                @else
                                    <span class="text-sm italic text-theme-muted">-</span>
                                @endif
                            </td>

                            <!-- Status LPJ -->
                            <td class="px-6 py-4 align-top text-center">
                                @if($item->status_lpj === 'belum')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-red-50 text-red-600 border border-red-200 dark:bg-red-500/10 dark:border-red-500/20">Menunggak</span>
                                @elseif($item->status_lpj === 'menunggu_verifikasi')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-amber-50 text-amber-600 border border-amber-200 dark:bg-amber-500/10 dark:border-amber-500/20">Proses Cek</span>
                                @elseif($item->status_lpj === 'selesai')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-600 border border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/20">Clear</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-theme-body mb-4 border border-theme-border shadow-inner">
                                    <svg class="w-10 h-10 text-theme-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                                </div>
                                <h4 class="text-base font-bold text-theme-text uppercase tracking-tight">Tidak Ada Data Realisasi</h4>
                                <p class="text-sm text-theme-muted mt-1">Belum ada dana yang dicairkan pada periode ini.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>