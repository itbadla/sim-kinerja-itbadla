<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\FundSubmission;
use App\Models\FundDisbursement;
use App\Models\Periode;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')] class extends Component {
    public $selectedPeriodeId = '';

    public function mount()
    {
        // PROTEKSI GLOBAL: Hanya user dengan permission 'verifikasi-keuangan' yang bisa mengakses
        abort_if(!Auth::user()->can('verifikasi-keuangan'), 403, 'Akses Ditolak: Anda tidak memiliki otoritas untuk melihat Dashboard Analitik Keuangan.');

        $currentPeriode = Periode::where('is_current', true)->first();
        if ($currentPeriode) {
            $this->selectedPeriodeId = $currentPeriode->id;
        }
    }

    public function updatingSelectedPeriodeId()
    {
        // Otomatis refresh data saat periode diganti
    }

    public function with(): array
    {
        $allPeriodes = Periode::orderBy('tanggal_mulai', 'desc')->get();
        $selectedPeriode = Periode::find($this->selectedPeriodeId);

        // Kumpulan Variabel Default
        $metrics = [
            'total_pengajuan' => 0,
            'total_disetujui' => 0,
            'total_realisasi' => 0,
            'total_silpa' => 0, // TAMBAHAN: Variabel Penampung SiLPA
            'persentase_serapan' => 0,
            
            'count_all' => 0,
            'count_pending' => 0,
            'count_approved' => 0,
            'count_rejected' => 0,
            
            'lpj_wajib' => 0,
            'lpj_belum' => 0,
            'lpj_menunggu' => 0,
            'lpj_selesai' => 0,
        ];

        $latestSubmissions = collect();

        if ($selectedPeriode) {
            // Base Query untuk Tabel Induk (Pengajuan)
            $baseSubmissions = FundSubmission::where('periode_id', $selectedPeriode->id);

            // 1. Kalkulasi Nominal Keuangan
            $metrics['total_pengajuan'] = (clone $baseSubmissions)->sum('nominal_total');
            $metrics['total_disetujui'] = (clone $baseSubmissions)->where('status_pengajuan', 'approved')->sum('nominal_disetujui');
            
            // Realisasi diambil dari Tabel Anak (Termin Pencairan) yang sudah selesai LPJ-nya
            $baseDisbursementSelesai = FundDisbursement::whereHas('submission', function($q) use ($selectedPeriode) {
                $q->where('periode_id', $selectedPeriode->id);
            })->where('status_lpj', 'selesai');

            $metrics['total_realisasi'] = (clone $baseDisbursementSelesai)->sum('nominal_realisasi');
            
            // TAMBAHAN: Akumulasi seluruh uang kembalian (SiLPA) yang sudah tervalidasi selesai
            $metrics['total_silpa'] = (clone $baseDisbursementSelesai)->sum('nominal_kembali');

            if ($metrics['total_disetujui'] > 0) {
                $metrics['persentase_serapan'] = round(($metrics['total_realisasi'] / $metrics['total_disetujui']) * 100, 1);
            }

            // 2. Kalkulasi Status Alur Pengajuan
            $metrics['count_all'] = (clone $baseSubmissions)->count();
            $metrics['count_pending'] = (clone $baseSubmissions)->where('status_pengajuan', 'pending')->count();
            $metrics['count_approved'] = (clone $baseSubmissions)->where('status_pengajuan', 'approved')->count();
            $metrics['count_rejected'] = (clone $baseSubmissions)->where('status_pengajuan', 'rejected')->count();

            // 3. Kalkulasi Kepatuhan LPJ (Hanya dihitung dari termin yang uangnya SUDAH CAIR ditransfer)
            $baseDisbursements = FundDisbursement::whereHas('submission', function($q) use ($selectedPeriode) {
                $q->where('periode_id', $selectedPeriode->id);
            })->where('status_cair', 'cair');

            $metrics['lpj_wajib'] = (clone $baseDisbursements)->count();
            $metrics['lpj_belum'] = (clone $baseDisbursements)->where('status_lpj', 'belum')->count();
            $metrics['lpj_menunggu'] = (clone $baseDisbursements)->where('status_lpj', 'menunggu_verifikasi')->count();
            $metrics['lpj_selesai'] = (clone $baseDisbursements)->where('status_lpj', 'selesai')->count();

            // 4. Ambil 5 Transaksi Pengajuan Terakhir
            $latestSubmissions = (clone $baseSubmissions)
                ->with(['user', 'unit'])
                ->latest('updated_at')
                ->take(5)
                ->get();
        }

        return [
            'allPeriodes' => $allPeriodes,
            'selectedPeriodeName' => $selectedPeriode->nama_periode ?? '-',
            'metrics' => $metrics,
            'latestSubmissions' => $latestSubmissions,
        ];
    }
}; ?>

<div class="space-y-6 relative max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 bg-white p-6 rounded-3xl border border-gray-200 shadow-sm">
        <div class="flex-1">
            <h1 class="text-2xl font-extrabold text-theme-text tracking-tight uppercase">Dashboard Analitik Keuangan</h1>
            <p class="text-sm text-theme-muted mt-1">Pantau arus kas, realisasi anggaran, dan kepatuhan LPJ pada periode <strong class="text-primary">{{ $selectedPeriodeName }}</strong>.</p>
        </div>
        
        <div class="w-full sm:w-64 shrink-0">
            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Tampilkan Data Periode</label>
            <select wire:model.live="selectedPeriodeId" class="w-full border-gray-300 bg-gray-50 rounded-xl text-sm font-bold text-gray-900 focus:ring-primary focus:border-primary shadow-sm cursor-pointer transition-all">
                <option value="">-- Pilih Periode --</option>
                @foreach($allPeriodes as $p)
                    <option value="{{ $p->id }}">{{ $p->nama_periode }} @if($p->is_current) (Aktif) @endif</option>
                @endforeach
            </select>
        </div>
    </div>

    @if(!$selectedPeriodeId)
        <div class="bg-amber-50 border border-amber-200 text-amber-700 rounded-2xl p-6 text-center shadow-sm">
            <svg class="w-12 h-12 text-amber-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            <h3 class="text-lg font-bold">Periode Belum Dipilih</h3>
            <p class="text-sm mt-1">Silakan pilih periode semester pada menu dropdown di atas untuk melihat analitik keuangan.</p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            
            <div class="bg-white p-5 rounded-3xl border border-gray-200 shadow-sm relative overflow-hidden group hover:border-blue-300 transition-colors">
                <div class="absolute -right-4 -top-4 w-20 h-20 bg-blue-50 rounded-full opacity-50 group-hover:scale-150 transition-transform duration-500"></div>
                <div class="relative z-10">
                    <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z"></path></svg>
                        Dana Diajukan
                    </p>
                    <h3 class="text-2xl font-black text-gray-900 truncate" title="Rp {{ number_format($metrics['total_pengajuan'], 0, ',', '.') }}">
                        Rp {{ number_format($metrics['total_pengajuan'], 0, ',', '.') }}
                    </h3>
                    <p class="text-xs text-gray-400 mt-2 font-medium">Total seluruh proposal masuk</p>
                </div>
            </div>

            <div class="bg-white p-5 rounded-3xl border border-gray-200 shadow-sm relative overflow-hidden group hover:border-emerald-300 transition-colors">
                <div class="absolute -right-4 -top-4 w-20 h-20 bg-emerald-50 rounded-full opacity-50 group-hover:scale-150 transition-transform duration-500"></div>
                <div class="relative z-10">
                    <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Dana Disetujui (ACC)
                    </p>
                    <h3 class="text-2xl font-black text-emerald-600 truncate" title="Rp {{ number_format($metrics['total_disetujui'], 0, ',', '.') }}">
                        Rp {{ number_format($metrics['total_disetujui'], 0, ',', '.') }}
                    </h3>
                    <p class="text-xs text-gray-400 mt-2 font-medium">Beban kewajiban realisasi</p>
                </div>
            </div>

            <div class="bg-white p-5 rounded-3xl border border-gray-200 shadow-sm relative overflow-hidden group hover:border-indigo-300 transition-colors">
                <div class="absolute -right-4 -top-4 w-20 h-20 bg-indigo-50 rounded-full opacity-50 group-hover:scale-150 transition-transform duration-500"></div>
                <div class="relative z-10">
                    <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Realisasi LPJ Selesai
                    </p>
                    <h3 class="text-2xl font-black text-indigo-700 truncate" title="Rp {{ number_format($metrics['total_realisasi'], 0, ',', '.') }}">
                        Rp {{ number_format($metrics['total_realisasi'], 0, ',', '.') }}
                    </h3>
                    
                    <!-- INFO SILPA DITAMPILKAN DI SINI -->
                    <div class="flex items-center justify-between mt-2">
                        <p class="text-xs text-gray-400 font-medium">Dana terserap.</p>
                        @if($metrics['total_silpa'] > 0)
                            <span class="text-[9px] font-bold bg-amber-50 text-amber-600 border border-amber-200 px-1.5 py-0.5 rounded uppercase tracking-wider" title="Total uang sisa yang dikembalikan ke Institusi">
                                SiLPA: Rp {{ number_format($metrics['total_silpa'], 0, ',', '.') }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-primary to-primary-hover p-5 rounded-3xl border border-primary-hover shadow-md relative overflow-hidden">
                <div class="absolute right-0 bottom-0 opacity-10">
                    <svg class="w-24 h-24" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"></path></svg>
                </div>
                <div class="relative z-10 text-white">
                    <p class="text-[10px] font-bold text-white/80 uppercase tracking-widest mb-1">Serapan Anggaran</p>
                    <div class="flex items-baseline gap-1">
                        <h3 class="text-3xl font-black">{{ $metrics['persentase_serapan'] }}</h3>
                        <span class="text-lg font-bold">%</span>
                    </div>
                    
                    <div class="w-full bg-white/20 rounded-full h-1.5 mt-3 mb-1">
                        <div class="bg-white h-1.5 rounded-full" style="width: {{ min($metrics['persentase_serapan'], 100) }}%"></div>
                    </div>
                    <p class="text-[9px] text-white/70 font-medium">Realisasi vs Disetujui</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-6">
            
            <div class="bg-white p-6 rounded-3xl border border-gray-200 shadow-sm flex flex-col">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Status Pengajuan Dana</h3>
                    <span class="px-3 py-1 bg-gray-100 text-gray-600 text-[10px] font-bold rounded-full">Total: {{ $metrics['count_all'] }}</span>
                </div>

                <div class="space-y-4 flex-1">
                    @php
                        $pctPending = $metrics['count_all'] > 0 ? ($metrics['count_pending'] / $metrics['count_all']) * 100 : 0;
                        $pctApproved = $metrics['count_all'] > 0 ? ($metrics['count_approved'] / $metrics['count_all']) * 100 : 0;
                        $pctRejected = $metrics['count_all'] > 0 ? ($metrics['count_rejected'] / $metrics['count_all']) * 100 : 0;
                    @endphp

                    <div>
                        <div class="flex justify-between text-xs font-bold mb-1">
                            <span class="text-emerald-700 flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Disetujui (ACC)</span>
                            <span class="text-gray-900">{{ $metrics['count_approved'] }} Berkas</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            <div class="bg-emerald-500 h-2 rounded-full transition-all duration-1000" style="width: {{ $pctApproved }}%"></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between text-xs font-bold mb-1">
                            <span class="text-amber-700 flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span> Menunggu Verifikasi</span>
                            <span class="text-gray-900">{{ $metrics['count_pending'] }} Berkas</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            <div class="bg-amber-400 h-2 rounded-full transition-all duration-1000" style="width: {{ $pctPending }}%"></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between text-xs font-bold mb-1">
                            <span class="text-red-700 flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-red-500"></span> Ditolak / Revisi</span>
                            <span class="text-gray-900">{{ $metrics['count_rejected'] }} Berkas</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            <div class="bg-red-500 h-2 rounded-full transition-all duration-1000" style="width: {{ $pctRejected }}%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-3xl border border-gray-200 shadow-sm flex flex-col">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Kepatuhan Laporan LPJ (Per Termin)</h3>
                    <span class="px-3 py-1 bg-blue-50 text-blue-700 border border-blue-200 text-[10px] font-bold rounded-full">Wajib Lapor: {{ $metrics['lpj_wajib'] }}</span>
                </div>

                <div class="space-y-4 flex-1">
                    @php
                        $pctLpjSelesai = $metrics['lpj_wajib'] > 0 ? ($metrics['lpj_selesai'] / $metrics['lpj_wajib']) * 100 : 0;
                        $pctLpjMenunggu = $metrics['lpj_wajib'] > 0 ? ($metrics['lpj_menunggu'] / $metrics['lpj_wajib']) * 100 : 0;
                        $pctLpjBelum = $metrics['lpj_wajib'] > 0 ? ($metrics['lpj_belum'] / $metrics['lpj_wajib']) * 100 : 0;
                    @endphp

                    <div>
                        <div class="flex justify-between text-xs font-bold mb-1">
                            <span class="text-indigo-700 flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-indigo-500"></span> Tuntas (Di-ACC)</span>
                            <span class="text-gray-900">{{ $metrics['lpj_selesai'] }} Termin ({{ round($pctLpjSelesai) }}%)</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            <div class="bg-indigo-500 h-2 rounded-full transition-all duration-1000" style="width: {{ $pctLpjSelesai }}%"></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between text-xs font-bold mb-1">
                            <span class="text-blue-700 flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-blue-500"></span> Sedang Diperiksa Keuangan</span>
                            <span class="text-gray-900">{{ $metrics['lpj_menunggu'] }} Termin</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            <div class="bg-blue-400 h-2 rounded-full transition-all duration-1000" style="width: {{ $pctLpjMenunggu }}%"></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between text-xs font-bold mb-1">
                            <span class="text-red-700 flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span> Belum / Telat Lapor (Hutang)</span>
                            <span class="text-gray-900">{{ $metrics['lpj_belum'] }} Termin</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            <div class="bg-red-500 h-2 rounded-full transition-all duration-1000" style="width: {{ $pctLpjBelum }}%"></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="mt-6 bg-white border border-gray-200 rounded-3xl overflow-hidden shadow-sm">
            <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-widest flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Aktivitas Pengajuan Terakhir
                </h3>
                <a href="{{ route('keuangan.verifikasi.index') }}" wire:navigate class="text-xs font-bold text-primary hover:text-primary-hover hover:underline transition-all">Lihat Semua Data &rarr;</a>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[800px]">
                    <thead class="bg-white border-b border-gray-100">
                        <tr>
                            <th class="px-6 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-widest w-1/3">Waktu & Pengaju</th>
                            <th class="px-6 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Keperluan</th>
                            <th class="px-6 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-widest text-right">Nominal Diajukan</th>
                            <th class="px-6 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-widest text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($latestSubmissions as $item)
                            <tr class="hover:bg-gray-50/30 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="text-[10px] text-gray-400 mb-0.5 font-medium">{{ $item->updated_at->diffForHumans() }}</div>
                                    <div class="text-sm font-bold text-gray-900">{{ $item->user->name ?? 'Unknown' }}</div>
                                    <div class="text-[10px] text-primary uppercase font-bold tracking-wider mt-0.5">{{ $item->unit->kode_unit ?? 'Pribadi' }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-xs font-medium text-gray-700 truncate max-w-xs">{{ $item->keperluan }}</p>
                                </td>
                                <td class="px-6 py-4 text-right font-extrabold text-gray-800 text-sm">
                                    Rp {{ number_format($item->nominal_total, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    @if($item->status_pengajuan === 'pending')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-widest bg-amber-50 text-amber-600 border border-amber-200">Pending</span>
                                    @elseif($item->status_pengajuan === 'approved')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-widest bg-emerald-50 text-emerald-600 border border-emerald-200">Disetujui</span>
                                    @elseif($item->status_pengajuan === 'rejected')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-widest bg-red-50 text-red-600 border border-red-200">Ditolak</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-xs text-gray-500 italic">Belum ada aktivitas pada periode ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>