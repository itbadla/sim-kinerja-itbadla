<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\FundSubmission;
use App\Models\FundDisbursement;
use App\Models\Periode;
use App\Models\Unit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination, WithFileUploads;

    public $search = '';
    public $filterStatusLpj = '';
    public $filterTipe = '';
    public $selectedPeriodeId = ''; 

    // ==========================================
    // STATE: MODAL FORM LPJ
    // ==========================================
    public $isModalOpen = false;
    public ?FundDisbursement $selectedDisbursement = null;
    
    // Field Input LPJ
    public $nominal_realisasi = '';
    public $file_lpj;
    public $file_lpj_lama;
    
    // Field Input Pengembalian (SiLPA)
    public $bukti_pengembalian;
    public $bukti_pengembalian_lama;

    public function mount()
    {
        $currentPeriode = Periode::where('is_current', true)->first();
        if ($currentPeriode) {
            $this->selectedPeriodeId = $currentPeriode->id;
        }
    }

    public function updatingSearch() { $this->resetPage(); }
    public function updatingFilterStatusLpj() { $this->resetPage(); }
    public function updatingFilterTipe() { $this->resetPage(); }
    public function updatingSelectedPeriodeId() { $this->resetPage(); }

    // ==========================================
    // FUNGSI: BUKA MODAL & BATALKAN LPJ
    // ==========================================
    public function openModal($id)
    {
        $this->resetValidation();
        $this->reset(['file_lpj', 'bukti_pengembalian']);
        
        $this->selectedDisbursement = FundDisbursement::with(['submission', 'submission.unit'])->findOrFail($id);
            
        $userId = Auth::id();
        $submission = $this->selectedDisbursement->submission;
        
        $isSubmitter = $submission->tipe_pengajuan === 'pribadi' && $submission->user_id === $userId;
        $isHeadOfUnit = false;
        
        if ($submission->tipe_pengajuan === 'lembaga' && $submission->unit_id) {
            $isHeadOfUnit = Unit::where('id', $submission->unit_id)
                                ->where('kepala_unit_id', $userId)
                                ->exists();
        }

        if (!$isSubmitter && !$isHeadOfUnit) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengubah LPJ ini.');
        }

        if ($this->selectedDisbursement->status_lpj === 'selesai') {
            session()->flash('error', 'LPJ yang sudah diverifikasi selesai tidak dapat diubah lagi.');
            return;
        }

        $this->nominal_realisasi = $this->selectedDisbursement->nominal_realisasi 
                                    ? round($this->selectedDisbursement->nominal_realisasi) 
                                    : round($this->selectedDisbursement->nominal_cair); 
                                    
        $this->file_lpj_lama = $this->selectedDisbursement->file_lpj;
        $this->bukti_pengembalian_lama = $this->selectedDisbursement->bukti_pengembalian;
        
        $this->isModalOpen = true;
    }

    public function cancelLpj($id)
    {
        $disbursement = FundDisbursement::with('submission')->findOrFail($id);
        
        $userId = Auth::id();
        $submission = $disbursement->submission;
        $isSubmitter = $submission->tipe_pengajuan === 'pribadi' && $submission->user_id === $userId;
        $isHeadOfUnit = $submission->tipe_pengajuan === 'lembaga' && $submission->unit_id && Unit::where('id', $submission->unit_id)->where('kepala_unit_id', $userId)->exists();
        
        if (!$isSubmitter && !$isHeadOfUnit) {
            abort(403, 'Akses ditolak.');
        }

        if ($disbursement->status_lpj === 'menunggu_verifikasi') {
            $disbursement->update([
                'status_lpj' => 'belum',
                'status_pengembalian' => 'tidak_ada'
            ]);
            session()->flash('success', 'Pengajuan LPJ berhasil ditarik dan dibatalkan. Anda dapat mengeditnya kembali.');
        } else {
            session()->flash('error', 'Hanya LPJ berstatus Menunggu Verifikasi yang dapat dibatalkan.');
        }
    }

    // ==========================================
    // FUNGSI: SIMPAN LPJ & KEMBALIAN (JIKA ADA)
    // ==========================================
    public function saveLpj()
    {
        if (!$this->selectedDisbursement) return;

        $nominalCair = floatval($this->selectedDisbursement->nominal_cair);
        $nominalRealisasi = floatval($this->nominal_realisasi);
        $nominalKembali = $nominalCair - $nominalRealisasi;

        $rules = [
            'nominal_realisasi' => 'required|numeric|min:0|max:' . $nominalCair, 
        ];

        $messages = [
            'file_lpj.required' => 'Dokumen bukti LPJ (struk/nota) wajib dilampirkan.',
            'bukti_pengembalian.required' => 'Karena ada sisa dana, bukti transfer pengembalian wajib dilampirkan.',
            'nominal_realisasi.max' => 'Nominal realisasi tidak boleh melebihi dana yang dicairkan pada termin ini (Rp '.number_format($nominalCair, 0, ',', '.').').',
        ];

        if (!$this->file_lpj_lama) {
            $rules['file_lpj'] = 'required|file|mimes:pdf,jpg,jpeg,png|max:5120'; 
        } else {
            $rules['file_lpj'] = 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120';
        }

        if ($nominalKembali > 0) {
            if (!$this->bukti_pengembalian_lama) {
                $rules['bukti_pengembalian'] = 'required|file|mimes:pdf,jpg,jpeg,png|max:3072';
            } else {
                $rules['bukti_pengembalian'] = 'nullable|file|mimes:pdf,jpg,jpeg,png|max:3072';
            }
        }

        $this->validate($rules, $messages);

        $data = [
            'nominal_realisasi' => $nominalRealisasi,
            'status_lpj' => 'menunggu_verifikasi', 
        ];

        if ($nominalKembali > 0) {
            $data['nominal_kembali'] = $nominalKembali;
            $data['status_pengembalian'] = 'menunggu_verifikasi';
            $data['waktu_pengembalian'] = now();
        } else {
            $data['nominal_kembali'] = 0;
            $data['status_pengembalian'] = 'tidak_ada';
            $data['bukti_pengembalian'] = null; 
        }

        if ($this->file_lpj) {
            if ($this->file_lpj_lama) {
                Storage::disk('public')->delete($this->file_lpj_lama);
            }
            $data['file_lpj'] = $this->file_lpj->store('lpj_files', 'public');
        }

        if ($nominalKembali > 0 && $this->bukti_pengembalian) {
            if ($this->bukti_pengembalian_lama) {
                Storage::disk('public')->delete($this->bukti_pengembalian_lama);
            }
            $data['bukti_pengembalian'] = $this->bukti_pengembalian->store('lpj_refunds', 'public');
        }

        $this->selectedDisbursement->update($data);
        session()->flash('success', 'Laporan LPJ berhasil dikirim untuk diverifikasi Keuangan!');

        $this->isModalOpen = false;
        $this->selectedDisbursement = null;
    }

    public function with(): array
    {
        $allPeriodes = Periode::orderBy('tanggal_mulai', 'desc')->get();
        $selectedPeriode = Periode::find($this->selectedPeriodeId);

        $groupedSubmissions = collect();
        $isHeadOfUnit = false;

        if ($selectedPeriode) {
            $userId = Auth::id();
            $headedUnitIds = Unit::where('kepala_unit_id', $userId)->pluck('id')->toArray();
            $isHeadOfUnit = count($headedUnitIds) > 0;

            $query = FundSubmission::with(['unit', 'user', 'disbursements' => function($q) {
                    $q->orderBy('termin_ke', 'asc');
                }])
                ->where('status_pengajuan', 'approved')
                ->where('periode_id', $selectedPeriode->id)
                ->whereHas('disbursements');

            // Filter Berdasarkan Tipe (Pribadi / Lembaga)
            $query->where(function ($q) use ($userId, $headedUnitIds) {
                if ($this->filterTipe === 'pribadi') {
                    $q->where('tipe_pengajuan', 'pribadi')
                      ->where('user_id', $userId);
                } elseif ($this->filterTipe === 'lembaga') {
                    $q->where('tipe_pengajuan', 'lembaga')
                      ->whereIn('unit_id', $headedUnitIds);
                } else {
                    // Default: Tampilkan keduanya jika kepala unit
                    $q->where(function($q1) use ($userId) {
                        $q1->where('tipe_pengajuan', 'pribadi')
                           ->where('user_id', $userId);
                    })
                    ->orWhere(function ($subQ) use ($headedUnitIds) {
                        $subQ->where('tipe_pengajuan', 'lembaga')
                             ->whereIn('unit_id', $headedUnitIds);
                    });
                }
            });

            if ($this->filterStatusLpj) {
                $status = $this->filterStatusLpj;
                $query->whereHas('disbursements', function($q) use ($status) {
                    if ($status === 'revisi') {
                        $q->where('status_lpj', 'belum')->whereNotNull('catatan_revisi_lpj');
                    } else {
                        $q->where('status_lpj', $status);
                    }
                });
            }

            if ($this->search) {
                $query->where('keperluan', 'like', '%' . $this->search . '%');
            }

            $groupedSubmissions = $query->latest()->paginate(10);
        }

        return [
            'groupedSubmissions' => $groupedSubmissions,
            'allPeriodes' => $allPeriodes,
            'selectedPeriode' => $selectedPeriode,
            'isHeadOfUnit' => $isHeadOfUnit,
        ];
    }
}; ?>

<div class="space-y-6 relative max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 bg-white p-6 rounded-3xl border border-gray-200 shadow-sm">
        <div class="flex-1">
            <h1 class="text-2xl font-extrabold text-theme-text tracking-tight uppercase">Laporan Pertanggungjawaban (LPJ)</h1>
            <p class="text-sm text-theme-muted mt-1">Unggah bukti transaksi dan kelola pengembalian dana (SiLPA) per termin pencairan.</p>
        </div>
        
        <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto">
            <div class="w-full sm:w-64">
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Pilih Periode Kinerja</label>
                <select wire:model.live="selectedPeriodeId" class="w-full border-gray-300 bg-gray-50 rounded-xl text-sm font-bold text-gray-900 focus:ring-primary focus:border-primary shadow-sm cursor-pointer transition-all">
                    <option value="">-- Pilih Periode --</option>
                    @foreach($allPeriodes as $p)
                        <option value="{{ $p->id }}">{{ $p->nama_periode }} @if($p->is_current) (Aktif) @endif</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    @if (session()->has('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl text-sm font-bold flex items-center gap-2 shadow-sm">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            {{ session('error') }}
        </div>
    @endif
    @if (session()->has('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-sm font-bold flex items-center gap-2 shadow-sm">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            {{ session('success') }}
        </div>
    @endif

    @if(!$selectedPeriode)
        <div class="bg-amber-50 border border-amber-200 text-amber-700 rounded-xl p-4 text-sm font-medium flex items-center gap-3 shadow-sm">
            <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            Sistem terkunci. Belum ada periode yang dipilih atau periode aktif belum tersedia.
        </div>
    @elseif($selectedPeriode->status === 'closed')
        <div class="bg-amber-50 border-l-4 border-amber-500 p-4 rounded-xl shadow-sm">
            <div class="flex items-center">
                <svg class="h-6 w-6 text-amber-500 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                <div>
                    <h3 class="text-sm font-bold text-amber-800">Periode {{ $selectedPeriode->nama_periode }} Ditutup</h3>
                    <p class="text-sm text-amber-700 mt-1">Periode ini telah diarsipkan. Anda tidak dapat merubah atau menambah pelaporan LPJ untuk data di periode ini.</p>
                </div>
            </div>
        </div>
    @endif

    @if($selectedPeriode)
    <div class="bg-white p-4 rounded-2xl border border-gray-200 shadow-sm flex flex-col md:flex-row items-center gap-3">
        @if($isHeadOfUnit)
        <div class="w-full md:w-auto flex-shrink-0">
            <select wire:model.live="filterTipe" class="block w-full border border-gray-300 bg-gray-50 rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-gray-900 font-medium transition-all">
                <option value="">Semua Tipe Laporan</option>
                <option value="pribadi">Pribadi</option>
                <option value="lembaga">Lembaga (Unit)</option>
            </select>
        </div>
        @endif

        <div class="w-full md:w-auto flex-shrink-0">
            <select wire:model.live="filterStatusLpj" class="block w-full border border-gray-300 bg-gray-50 rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-gray-900 font-medium transition-all">
                <option value="">Semua Status LPJ</option>
                <option value="belum">Belum Dilaporkan</option>
                <option value="revisi">❌ Sedang Direvisi</option>
                <option value="menunggu_verifikasi">⏳ Proses Verifikasi Keuangan</option>
                <option value="selesai">✅ LPJ Selesai (Clear)</option>
            </select>
        </div>

        <div class="relative w-full">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </span>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari rincian keperluan pengajuan..." class="block w-full pl-10 pr-3 py-2.5 border border-gray-300 bg-gray-50 rounded-xl focus:ring-primary focus:border-primary text-sm text-gray-900 transition-all">
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-3xl overflow-hidden shadow-sm mt-4">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[900px]">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest w-48">Info Termin & Status Cair</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest text-right">Dana Cair Termin</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest text-right">Realisasi & Sisa (SiLPA)</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest text-center w-36">Status LPJ</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest text-right w-36">Aksi Lapor</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($groupedSubmissions as $sub)
                        <!-- Header Kelompok Pengajuan (Submission Induk) -->
                        <tr class="bg-indigo-50/40 border-t-2 border-indigo-100">
                            <td colspan="5" class="px-6 py-4">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <div class="flex items-center gap-2 mb-1.5">
                                            @if($sub->tipe_pengajuan === 'lembaga')
                                                <span class="text-sm font-bold text-gray-900 flex items-center gap-2">
                                                    {{ $sub->unit->nama_unit ?? 'Unit' }}
                                                </span>
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-blue-100 text-blue-700 border border-blue-200">
                                                    Milik Lembaga
                                                </span>
                                                <span class="text-[10px] text-gray-500 italic ml-1">(Diajukan oleh: {{ $sub->user->name ?? 'Unknown' }})</span>
                                            @else
                                                <span class="text-sm font-bold text-gray-900 flex items-center gap-2">
                                                    {{ $sub->user->name ?? 'Unknown' }}
                                                </span>
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-700 border border-emerald-200">
                                                    Pribadi
                                                </span>
                                            @endif
                                            
                                            <span class="text-[10px] text-gray-500 font-bold flex items-center gap-1 ml-2">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                {{ $sub->created_at->translatedFormat('d M Y') }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-indigo-900 font-medium leading-relaxed max-w-3xl mt-2">{{ Str::limit($sub->keperluan, 120) }}</p>
                                    </div>
                                    <div class="text-right shrink-0 ml-4">
                                        <div class="text-[10px] text-indigo-500 font-bold uppercase tracking-widest mb-0.5">Total Di-ACC</div>
                                        <div class="text-base font-black text-indigo-700">Rp {{ number_format($sub->nominal_disetujui, 0, ',', '.') }}</div>
                                        <div class="text-[9px] font-bold uppercase bg-white border border-indigo-200 text-indigo-500 px-1.5 py-0.5 rounded mt-1 inline-block shadow-sm">
                                            {{ $sub->skema_pencairan === 'termin' ? $sub->disbursements->count().' Termin' : 'Lumpsum' }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <!-- Baris Anak (Termin per Pengajuan) -->
                        @foreach($sub->disbursements as $item)
                            <tr class="hover:bg-gray-50/50 transition-colors align-middle" wire:key="lpj-{{ $item->id }}">
                                
                                <td class="px-6 py-5 relative pl-12">
                                    <div class="absolute left-6 top-0 bottom-0 w-px bg-indigo-200 {{ $loop->last ? 'h-1/2' : '' }}"></div>
                                    <div class="absolute left-6 top-1/2 w-4 h-px bg-indigo-200"></div>
                                    
                                    <div class="flex items-center gap-1.5 mb-2 relative z-10">
                                        <span class="inline-flex px-2 py-0.5 bg-white border border-indigo-200 text-indigo-700 rounded text-[9px] font-bold uppercase tracking-widest shadow-sm">
                                            Termin {{ $item->termin_ke }}
                                        </span>
                                    </div>
                                    
                                    @if($item->status_cair === 'cair')
                                        <div class="inline-flex items-center gap-1 px-2 py-1 rounded text-[9px] font-bold uppercase bg-emerald-50 text-emerald-600 border border-emerald-200 mb-1 relative z-10">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                            Telah Cair
                                        </div>
                                        @if($item->tanggal_cair)
                                            <div class="text-[9px] text-gray-500 font-medium relative z-10">{{ \Carbon\Carbon::parse($item->tanggal_cair)->translatedFormat('d M Y') }}</div>
                                        @endif
                                    @else
                                        <div class="inline-flex items-center gap-1 px-2 py-1 rounded text-[9px] font-bold uppercase bg-amber-50 text-amber-600 border border-amber-200 relative z-10">
                                            <span class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-pulse"></span>
                                            Menunggu Transfer
                                        </div>
                                    @endif
                                </td>

                                <td class="px-6 py-5 text-right">
                                    <div class="text-sm font-extrabold text-gray-900">Rp {{ number_format($item->nominal_cair, 0, ',', '.') }}</div>
                                </td>
                                
                                <td class="px-6 py-5 text-right">
                                    @if($item->nominal_realisasi !== null)
                                        <div class="text-sm font-bold text-blue-600">Rp {{ number_format($item->nominal_realisasi, 0, ',', '.') }}</div>
                                        
                                        @if($item->nominal_kembali > 0)
                                            <div class="mt-1 text-[9px] font-bold text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200 inline-block uppercase tracking-wider shadow-sm" title="Sisa dana ini harus dikembalikan ke kampus">
                                                Sisa: Rp {{ number_format($item->nominal_kembali, 0, ',', '.') }}
                                            </div>
                                        @else
                                            <div class="mt-1 text-[9px] font-bold text-gray-400 uppercase tracking-widest inline-block">
                                                Dana Habis Terserap
                                            </div>
                                        @endif
                                    @else
                                        <div class="text-sm italic text-gray-400">-</div>
                                    @endif
                                </td>

                                <td class="px-6 py-5 text-center">
                                    @if($item->status_lpj === 'belum' && !$item->catatan_revisi_lpj)
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-gray-100 text-gray-600 border border-gray-200">Belum Lapor</span>
                                    @elseif($item->status_lpj === 'belum' && $item->catatan_revisi_lpj)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-red-50 text-red-600 border border-red-200 shadow-sm">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                            Revisi
                                        </span>
                                    @elseif($item->status_lpj === 'menunggu_verifikasi')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-amber-50 text-amber-600 border border-amber-200 shadow-sm">
                                            <span class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-pulse mr-1"></span> Proses Cek
                                        </span>
                                    @elseif($item->status_lpj === 'selesai')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200 shadow-sm">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                            Selesai
                                        </span>
                                    @endif
                                    
                                    @if($item->file_lpj)
                                        <a href="{{ Storage::url($item->file_lpj) }}" target="_blank" class="mt-2 text-[10px] flex items-center justify-center w-full gap-1 text-gray-500 font-bold hover:text-primary transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                                            Lihat LPJ
                                        </a>
                                    @endif
                                </td>

                                <td class="px-6 py-5 text-right">
                                    @if($item->status_cair !== 'cair')
                                        <div class="flex flex-col items-end gap-1">
                                            <span class="text-[10px] text-amber-600 font-bold uppercase tracking-widest text-right leading-tight">Menunggu Dana</span>
                                            <span class="text-[9px] text-gray-400 text-right">Belum bisa upload LPJ</span>
                                        </div>
                                    @elseif($item->status_lpj === 'menunggu_verifikasi')
                                        <div class="flex flex-col items-end gap-1">
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-[9px] font-bold uppercase bg-amber-50 text-amber-600 border border-amber-200">
                                                Sedang Diaudit
                                            </span>
                                            <button wire:click="cancelLpj({{ $item->id }})" wire:confirm="Yakin ingin menarik kembali laporan LPJ ini?" class="text-[9px] text-red-500 hover:text-red-700 underline font-bold uppercase tracking-wider transition-colors mt-1">
                                                Batalkan Laporan
                                            </button>
                                        </div>
                                    @elseif($item->status_lpj !== 'selesai' && $selectedPeriode->status !== 'closed')
                                        <button wire:click="openModal({{ $item->id }})" class="inline-flex items-center gap-1.5 px-4 py-2 {{ $item->catatan_revisi_lpj ? 'bg-red-50 text-red-600 border border-red-200 hover:bg-red-100' : 'bg-primary text-white hover:bg-primary-hover' }} text-xs font-bold rounded-xl transition-all shadow-sm">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                @if($item->catatan_revisi_lpj)
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                                @else
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                                                @endif
                                            </svg>
                                            {{ $item->catatan_revisi_lpj ? 'Perbaiki' : 'Upload LPJ' }}
                                        </button>
                                        @if($item->catatan_revisi_lpj)
                                            <p class="text-[9px] text-red-500 italic mt-1 text-right max-w-[120px] truncate ml-auto" title="{{ $item->catatan_revisi_lpj }}">Ada catatan revisi</p>
                                        @endif
                                    @else
                                        <div class="flex justify-end pr-2">
                                            <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="Selesai atau Periode Terkunci"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-16 text-center">
                                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4 border border-gray-200 shadow-inner">
                                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                </div>
                                <h4 class="text-sm font-bold text-gray-900 uppercase tracking-tight">Tidak Ada Data Pengajuan</h4>
                                <p class="text-xs text-gray-500 mt-1">Anda belum memiliki dana yang dijadwalkan cair pada filter ini.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($groupedSubmissions->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $groupedSubmissions->links() }}
            </div>
        @endif
    </div>
    @endif

    <!-- ========================================== -->
    <!-- MODAL FORM LPJ & PENGEMBALIAN -->
    <!-- ========================================== -->
    @if($isModalOpen && $selectedDisbursement)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4 transition-opacity" style="pointer-events: auto;">
            <div class="bg-white rounded-3xl border border-gray-200 shadow-2xl w-full max-w-2xl flex flex-col max-h-[90vh] overflow-hidden" onclick="event.stopPropagation()">
                
                <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center z-10 shrink-0">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 leading-tight">Form Pertanggungjawaban</h3>
                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mt-1">Termin {{ $selectedDisbursement->termin_ke }}</p>
                    </div>
                    <button type="button" wire:click="$set('isModalOpen', false)" class="text-gray-400 hover:text-red-500 transition-colors p-2 bg-white rounded-lg border border-gray-200 shadow-sm hover:bg-red-50 hover:border-red-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <form wire:submit.prevent="saveLpj" class="flex flex-col overflow-hidden flex-1">
                    <div class="p-6 overflow-y-auto custom-scrollbar space-y-6">
                        
                        @if($selectedDisbursement->catatan_revisi_lpj)
                            <div class="bg-red-50 p-4 rounded-2xl border border-red-200 shadow-sm">
                                <h4 class="text-xs font-black text-red-800 uppercase tracking-widest mb-1 flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                    Catatan Revisi Keuangan
                                </h4>
                                <p class="text-sm font-medium text-red-700 italic">"{{ $selectedDisbursement->catatan_revisi_lpj }}"</p>
                            </div>
                        @endif

                        <div class="bg-blue-50 p-5 rounded-2xl border border-blue-200 text-center shadow-inner">
                            <p class="text-[10px] font-bold text-blue-600 uppercase tracking-wider mb-1">Total Dana Termin Ini</p>
                            <p class="text-3xl font-black text-gray-900">Rp {{ number_format($selectedDisbursement->nominal_cair, 0, ',', '.') }}</p>
                            <p class="text-xs text-gray-600 mt-2 font-medium bg-white/50 p-2 rounded-lg inline-block border border-blue-100">
                                <strong>Kegiatan:</strong> {{ Str::limit($selectedDisbursement->submission->keperluan, 80) }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Nominal Realisasi Terpakai (Rp) <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-500 font-black text-lg">Rp</span>
                                <input type="number" wire:model.live.debounce.500ms="nominal_realisasi" min="0" max="{{ $selectedDisbursement->nominal_cair }}" class="block w-full pl-12 pr-4 py-3 border border-gray-300 bg-white rounded-2xl focus:ring-primary focus:border-primary text-lg text-gray-900 font-black shadow-sm">
                            </div>
                            <p class="text-[10px] text-gray-500 mt-1.5 font-medium">Masukkan total riil belanja sesuai dengan struk/nota yang valid.</p>
                            @error('nominal_realisasi') <span class="text-xs text-red-500 mt-1 block font-bold">{{ $message }}</span> @enderror
                        </div>

                        @php 
                            $sisaDana = floatval($selectedDisbursement->nominal_cair) - floatval($nominal_realisasi); 
                        @endphp
                        
                        @if($sisaDana > 0 && $nominal_realisasi !== '')
                            <div class="bg-amber-50 border border-amber-200 p-5 rounded-2xl space-y-4 shadow-sm">
                                <div class="flex items-center justify-between border-b border-amber-200/50 pb-3">
                                    <div>
                                        <p class="text-[10px] font-black text-amber-800 uppercase tracking-widest">Sisa Dana (Belum Terserap)</p>
                                        <p class="text-xl font-black text-amber-600">Rp {{ number_format($sisaDana, 0, ',', '.') }}</p>
                                    </div>
                                    <svg class="w-8 h-8 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>
                                
                                <div>
                                    <label class="block text-[10px] font-bold text-amber-800 uppercase tracking-wider mb-1.5">Bukti Transfer Pengembalian Sisa Dana <span class="text-red-500">*</span></label>
                                    <input type="file" wire:model="bukti_pengembalian" class="block w-full border border-amber-200 bg-white rounded-xl py-2 px-3 text-sm text-gray-700 shadow-sm file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-[10px] file:font-bold file:uppercase file:bg-amber-100 file:text-amber-700 hover:file:bg-amber-200 cursor-pointer transition-all">
                                    <div wire:loading wire:target="bukti_pengembalian" class="text-[10px] font-bold text-amber-600 mt-1 animate-pulse">Mengunggah file...</div>
                                    
                                    @if($bukti_pengembalian_lama && !$bukti_pengembalian)
                                        <div class="text-[10px] font-bold text-blue-600 mt-2 bg-blue-50 px-2 py-1 rounded inline-block">Sudah ada bukti transfer terlampir</div>
                                    @endif
                                    @error('bukti_pengembalian') <span class="text-xs text-red-500 mt-1 block font-bold">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        @endif

                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">File Laporan / Scan Kuitansi <span class="text-red-500">*</span></label>
                            <div class="border-2 border-dashed border-gray-300 rounded-2xl p-5 text-center hover:bg-gray-50 transition-colors relative bg-white">
                                <input type="file" wire:model="file_lpj" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                                
                                <div class="pointer-events-none">
                                    <svg class="mx-auto h-10 w-10 text-gray-300 mb-2" stroke="currentColor" fill="none" viewBox="0 0 48 48"><path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                    <p class="text-sm font-bold text-primary">Klik area ini untuk memilih file</p>
                                    <p class="text-[10px] text-gray-400 mt-1 font-medium">Format PDF atau JPG/PNG. Maksimal 5MB. Jika ada banyak kuitansi, gabungkan dalam 1 file PDF.</p>
                                </div>
                            </div>
                            
                            <div wire:loading wire:target="file_lpj" class="text-[10px] font-bold text-primary mt-2">
                                Mengunggah file...
                            </div>

                            @if($file_lpj)
                                <div class="text-[11px] font-bold text-emerald-700 mt-2 bg-emerald-50 px-3 py-2 rounded-lg border border-emerald-200 w-full shadow-sm flex items-center gap-2">
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> 
                                    File siap disubmit: {{ $file_lpj->getClientOriginalName() }}
                                </div>
                            @elseif($file_lpj_lama)
                                <div class="text-[11px] font-bold text-blue-700 mt-2 bg-blue-50 px-3 py-2 rounded-lg border border-blue-200 w-full shadow-sm flex items-center gap-2">
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg> 
                                    File LPJ / Kuitansi sudah terlampir.
                                </div>
                            @endif

                            @error('file_lpj') <span class="text-xs text-red-500 mt-2 block font-bold">{{ $message }}</span> @enderror
                        </div>

                    </div>
                    
                    <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex justify-end gap-3 shrink-0">
                        <button type="button" wire:click="$set('isModalOpen', false)" class="px-5 py-2.5 text-sm font-bold text-gray-500 hover:text-gray-900 bg-white border border-gray-200 shadow-sm rounded-xl transition-colors">
                            Batal
                        </button>
                        
                        <button type="submit" wire:loading.attr="disabled" class="px-6 py-2.5 text-sm font-bold text-white bg-primary hover:bg-primary-hover rounded-xl shadow-md transition-all flex items-center gap-2 disabled:opacity-50">
                            <span wire:loading.remove wire:target="saveLpj">Kirim Laporan</span>
                            <span wire:loading wire:target="saveLpj">Memproses...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>