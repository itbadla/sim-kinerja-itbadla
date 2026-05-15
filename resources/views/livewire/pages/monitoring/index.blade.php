<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Periode;
use App\Models\WorkProgram;
use App\Models\FundSubmission;
use App\Models\FundDisbursement;
use App\Models\Unit;
use App\Models\Logbook;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new #[Layout('layouts.app')] class extends Component {
    public function with(): array
    {
        $periodeAktif = Periode::where('is_current', true)->first();
        $periodeId = $periodeAktif->id ?? 0; // Fallback ke 0 jika tidak ada agar query tidak error

        // 1. Pagu Global (Total Rencana Anggaran dari proker yang DISETUJUI se-kampus)
        $paguGlobal = WorkProgram::where('periode_id', $periodeId)
            ->where('status', 'disetujui')
            ->sum('anggaran_rencana');

        // 2. Total Dana Cair Global (Aman dari missing relationship)
        $submissionIds = FundSubmission::where('periode_id', $periodeId)->pluck('id');
        $danaCairGlobal = FundDisbursement::whereIn('fund_submission_id', $submissionIds)
            ->where('status_cair', 'cair')
            ->sum('nominal_cair');

        // 3. Sisa Anggaran & Persentase
        $sisaAnggaranGlobal = $paguGlobal - $danaCairGlobal;
        $persentaseSerapan = $paguGlobal > 0 ? round(($danaCairGlobal / $paguGlobal) * 100, 1) : 0;

        // 4. Alert LPJ Tertunggak Se-Kampus (Penting untuk Pimpinan)
        $lpjPendingCount = FundDisbursement::whereIn('fund_submission_id', $submissionIds)
            ->where('status_cair', 'cair')
            ->whereIn('status_lpj', ['belum', 'menunggu_verifikasi'])
            ->count();

        $keuanganGlobal = [
            'pagu' => $paguGlobal,
            'cair' => $danaCairGlobal,
            'sisa' => $sisaAnggaranGlobal,
            'persentase' => $persentaseSerapan,
            'lpj_tertunggak' => $lpjPendingCount
        ];

        $prokerGlobal = [
            'total' => WorkProgram::where('periode_id', $periodeId)->count(),
            'disetujui' => WorkProgram::where('periode_id', $periodeId)->where('status', 'disetujui')->count(),
            'review' => WorkProgram::where('periode_id', $periodeId)->where('status', 'review_lpm')->count(),
            'draft' => WorkProgram::where('periode_id', $periodeId)->where('status', 'draft')->count(),
        ];

        $awalBulan = Carbon::now()->startOfMonth();
        $akhirBulan = Carbon::now()->endOfMonth();

        $kinerjaSDM = [
            'logbook_total' => Logbook::whereBetween('tanggal', [$awalBulan, $akhirBulan])->count(),
            'logbook_pending' => Logbook::whereBetween('tanggal', [$awalBulan, $akhirBulan])->whereIn('status', ['draft', 'pending'])->count(),
            'bkd_total' => DB::table('bkd_activities')->where('periode_id', $periodeId)->count(),
            'bkd_approved' => DB::table('bkd_activities')->where('periode_id', $periodeId)->where('status_internal', 'approved')->count(),
            'bkd_synced' => DB::table('bkd_activities')->where('periode_id', $periodeId)->where('sync_status', 'synced')->count(),
        ];

        // Mengambil unit yang tidak punya parent (Top Level)
        $serapanPerUnit = Unit::whereNull('parent_id')->get()->map(function($unit) use ($periodeId) {
            // Ambil ID unit ini dan ID anak-anaknya (misal Fakultas + Prodi di bawahnya)
            $childIds = Unit::where('parent_id', $unit->id)->pluck('id')->toArray();
            $childIds[] = $unit->id; // Masukkan dirinya sendiri

            // Pagu Unit (Gabungan)
            $pagu = WorkProgram::whereIn('unit_id', $childIds)
                ->where('periode_id', $periodeId)
                ->where('status', 'disetujui')
                ->sum('anggaran_rencana');
            
            // Dana Cair Unit
            $subIds = FundSubmission::whereIn('unit_id', $childIds)->where('periode_id', $periodeId)->pluck('id');
            $cair = FundDisbursement::whereIn('fund_submission_id', $subIds)
                ->where('status_cair', 'cair')
                ->sum('nominal_cair');
            
            return [
                'nama_unit' => $unit->nama_unit,
                'pagu' => $pagu,
                'cair' => $cair,
                'persentase' => $pagu > 0 ? round(($cair / $pagu) * 100, 1) : 0
            ];
        })->sortByDesc('persentase')->values(); // Urutkan dari serapan tertinggi

        return [
            'periodeAktif' => $periodeAktif,
            'keuanganGlobal' => $keuanganGlobal,
            'prokerGlobal' => $prokerGlobal,
            'kinerjaSDM' => $kinerjaSDM,
            'serapanPerUnit' => $serapanPerUnit,
        ];
    }
}; ?>

<div class="space-y-6 pb-10">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-theme-surface p-6 rounded-3xl border border-theme-border shadow-sm">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-theme-text tracking-tight">Monitoring Eksekutif</h1>
                <p class="text-sm text-theme-muted mt-1">Laporan Kinerja & Keuangan Kampus Real-time.</p>
            </div>
        </div>
        @if($periodeAktif ?? null)
            <div class="px-5 py-2.5 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 rounded-xl flex items-center gap-2">
                <span class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse"></span>
                <span class="text-sm font-bold text-emerald-700 dark:text-emerald-400">Tahun Ajaran: {{ $periodeAktif->nama_periode }}</span>
            </div>
        @else
            <div class="px-5 py-2.5 bg-rose-50 border border-rose-200 rounded-xl flex items-center gap-2">
                <span class="w-2.5 h-2.5 bg-rose-500 rounded-full"></span>
                <span class="text-sm font-bold text-rose-700">Belum ada periode aktif!</span>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Pagu -->
        <div class="bg-theme-surface p-5 rounded-2xl border border-theme-border shadow-sm relative overflow-hidden">
            <h3 class="text-xs font-bold text-theme-muted uppercase tracking-wider mb-2">Total Pagu Kampus</h3>
            <p class="text-2xl font-extrabold text-theme-text">Rp {{ number_format($keuanganGlobal['pagu'], 0, ',', '.') }}</p>
            <div class="absolute -right-4 -bottom-4 opacity-5">
                <svg class="w-24 h-24" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"></path><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"></path></svg>
            </div>
        </div>

        <!-- Serapan -->
        <div class="bg-primary/5 dark:bg-primary/10 p-5 rounded-2xl border border-primary/20 shadow-sm relative overflow-hidden">
            <div class="flex justify-between items-start mb-2">
                <h3 class="text-xs font-bold text-primary uppercase tracking-wider">Total Serapan Dana</h3>
                <span class="px-2 py-0.5 bg-primary text-white text-[10px] font-bold rounded">{{ $keuanganGlobal['persentase'] }}%</span>
            </div>
            <p class="text-2xl font-extrabold text-primary">Rp {{ number_format($keuanganGlobal['cair'], 0, ',', '.') }}</p>
        </div>

        <!-- Sisa -->
        <div class="bg-theme-surface p-5 rounded-2xl border border-theme-border shadow-sm">
            <h3 class="text-xs font-bold text-theme-muted uppercase tracking-wider mb-2">Sisa Anggaran / SiLPA</h3>
            <p class="text-2xl font-extrabold text-theme-text">Rp {{ number_format($keuanganGlobal['sisa'], 0, ',', '.') }}</p>
        </div>

        <!-- Peringatan LPJ -->
        <div class="{{ $keuanganGlobal['lpj_tertunggak'] > 0 ? 'bg-rose-50 border-rose-200 dark:bg-rose-500/10 dark:border-rose-500/20' : 'bg-theme-surface border-theme-border' }} p-5 rounded-2xl border shadow-sm">
            <h3 class="text-xs font-bold {{ $keuanganGlobal['lpj_tertunggak'] > 0 ? 'text-rose-600' : 'text-theme-muted' }} uppercase tracking-wider mb-2">LPJ Tertunggak</h3>
            <div class="flex items-end gap-2">
                <p class="text-2xl font-extrabold {{ $keuanganGlobal['lpj_tertunggak'] > 0 ? 'text-rose-700 dark:text-rose-400' : 'text-theme-text' }}">{{ $keuanganGlobal['lpj_tertunggak'] }}</p>
                <p class="text-xs font-semibold mb-1 {{ $keuanganGlobal['lpj_tertunggak'] > 0 ? 'text-rose-600' : 'text-theme-muted' }}">Berkas</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- Status Proker Kampus -->
        <div class="bg-theme-surface p-6 rounded-3xl border border-theme-border shadow-sm">
            <h3 class="text-sm font-bold text-theme-text uppercase tracking-wider mb-6 flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                Status Program Kerja Lembaga/Unit
            </h3>
            
            <div class="flex flex-col gap-4">
                <div>
                    <div class="flex justify-between text-xs font-bold mb-1">
                        <span class="text-emerald-600">Disetujui & Berjalan ({{ $prokerGlobal['disetujui'] }})</span>
                        <span>{{ $prokerGlobal['total'] > 0 ? round(($prokerGlobal['disetujui'] / $prokerGlobal['total']) * 100) : 0 }}%</span>
                    </div>
                    <div class="w-full bg-theme-body rounded-full h-2">
                        <div class="bg-emerald-500 h-2 rounded-full" style="width: {{ $prokerGlobal['total'] > 0 ? ($prokerGlobal['disetujui'] / $prokerGlobal['total']) * 100 : 0 }}%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-xs font-bold mb-1">
                        <span class="text-amber-600">Proses Review ({{ $prokerGlobal['review'] }})</span>
                        <span>{{ $prokerGlobal['total'] > 0 ? round(($prokerGlobal['review'] / $prokerGlobal['total']) * 100) : 0 }}%</span>
                    </div>
                    <div class="w-full bg-theme-body rounded-full h-2">
                        <div class="bg-amber-500 h-2 rounded-full" style="width: {{ $prokerGlobal['total'] > 0 ? ($prokerGlobal['review'] / $prokerGlobal['total']) * 100 : 0 }}%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-xs font-bold mb-1">
                        <span class="text-theme-muted">Draft ({{ $prokerGlobal['draft'] }})</span>
                        <span>{{ $prokerGlobal['total'] > 0 ? round(($prokerGlobal['draft'] / $prokerGlobal['total']) * 100) : 0 }}%</span>
                    </div>
                    <div class="w-full bg-theme-body rounded-full h-2">
                        <div class="bg-gray-400 h-2 rounded-full" style="width: {{ $prokerGlobal['total'] > 0 ? ($prokerGlobal['draft'] / $prokerGlobal['total']) * 100 : 0 }}%"></div>
                    </div>
                </div>
            </div>
            
            <div class="mt-6 pt-4 border-t border-theme-border">
                <p class="text-xs text-center text-theme-muted">Total Keseluruhan: <strong>{{ $prokerGlobal['total'] }} Program Kerja</strong> diajukan pada periode ini.</p>
            </div>
        </div>

        <!-- Kinerja SDM & BKD -->
        <div class="bg-theme-surface p-6 rounded-3xl border border-theme-border shadow-sm flex flex-col justify-between">
            <h3 class="text-sm font-bold text-theme-text uppercase tracking-wider mb-6 flex items-center gap-2">
                <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                Pemantauan Kinerja SDM (Tridharma)
            </h3>
            
            <div class="grid grid-cols-2 gap-4">
                <!-- Logbook Boks -->
                <div class="bg-theme-body p-4 rounded-xl border border-theme-border">
                    <p class="text-[10px] font-bold text-theme-muted uppercase mb-2">Logbook Harian (Bulan Ini)</p>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-extrabold text-theme-text">{{ $kinerjaSDM['logbook_total'] }}</span>
                        <span class="text-xs text-theme-muted">Entri</span>
                    </div>
                    @if($kinerjaSDM['logbook_pending'] > 0)
                        <p class="text-xs font-semibold text-amber-600 mt-2">{{ $kinerjaSDM['logbook_pending'] }} antre verifikasi</p>
                    @else
                        <p class="text-xs font-semibold text-emerald-600 mt-2">Semua terverifikasi</p>
                    @endif
                </div>

                <!-- BKD Boks -->
                <div class="bg-theme-body p-4 rounded-xl border border-theme-border">
                    <p class="text-[10px] font-bold text-theme-muted uppercase mb-2">Kinerja BKD (Periode Ini)</p>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-extrabold text-theme-text">{{ $kinerjaSDM['bkd_total'] }}</span>
                        <span class="text-xs text-theme-muted">Kegiatan</span>
                    </div>
                    <div class="mt-2 text-xs font-semibold flex flex-col gap-0.5">
                        <span class="text-emerald-600">{{ $kinerjaSDM['bkd_approved'] }} Disetujui Internal</span>
                        <span class="text-blue-600">{{ $kinerjaSDM['bkd_synced'] }} Tersinkron SISTER</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-theme-surface rounded-3xl border border-theme-border shadow-sm overflow-hidden">
        <div class="p-6 border-b border-theme-border flex justify-between items-center">
            <h3 class="text-sm font-bold text-theme-text uppercase tracking-wider flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                Peringkat Serapan Anggaran Fakultas / Lembaga
            </h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-theme-text">
                <thead class="bg-theme-body text-xs uppercase text-theme-muted">
                    <tr>
                        <th scope="col" class="px-6 py-4 font-bold">Nama Unit Utama</th>
                        <th scope="col" class="px-6 py-4 font-bold">Pagu Anggaran</th>
                        <th scope="col" class="px-6 py-4 font-bold">Dana Terealisasi (Cair)</th>
                        <th scope="col" class="px-6 py-4 font-bold w-1/4">Persentase Serapan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-theme-border">
                    @forelse($serapanPerUnit as $unit)
                        <tr class="hover:bg-theme-body/50 transition-colors">
                            <td class="px-6 py-4 font-bold text-theme-text">
                                {{ $unit['nama_unit'] }}
                            </td>
                            <td class="px-6 py-4 font-semibold text-theme-muted">
                                Rp {{ number_format($unit['pagu'], 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 font-bold text-emerald-600">
                                Rp {{ number_format($unit['cair'], 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="font-extrabold w-10 text-right {{ $unit['persentase'] < 30 ? 'text-rose-500' : ($unit['persentase'] < 70 ? 'text-amber-500' : 'text-emerald-500') }}">{{ $unit['persentase'] }}%</span>
                                    <div class="w-full bg-theme-body rounded-full h-2">
                                        <div class="{{ $unit['persentase'] < 30 ? 'bg-rose-500' : ($unit['persentase'] < 70 ? 'bg-amber-500' : 'bg-emerald-500') }} h-2 rounded-full" style="width: {{ min($unit['persentase'], 100) }}%"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-theme-muted italic">
                                Belum ada data unit atau program kerja yang disetujui.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>