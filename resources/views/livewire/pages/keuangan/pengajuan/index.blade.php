<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\FundSubmission;
use App\Models\Unit;
use App\Models\Periode;
use App\Models\WorkProgram;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination, WithFileUploads;

    public $search = '';
    public $filterStatus = '';
    public $filterTipe = '';
    public $selectedPeriodeId = ''; 

    // ==========================================
    // STATE: MODAL FORM
    // ==========================================
    public $isModalOpen = false;
    public $submissionId = null;
    
    // Field Form Sesuai DB Baru
    public $tipe_pengajuan = 'pribadi';
    public $unit_id = ''; 
    public $work_program_id = ''; 
    public $nominal_total = ''; 
    public $skema_pencairan = 'lumpsum'; 
    public $jumlah_termin = 2;
    public $keperluan = '';
    public $file_lampiran;
    public $file_lampiran_lama;

    // Data User & Otoritas
    public $isKepalaUnit = false;
    public $headedUnits = [];

    // ==========================================
    // STATE: MODAL DELETE & CATATAN
    // ==========================================
    public $isDeleteModalOpen = false;
    public ?int $submissionToDeleteId = null;

    public $isCatatanModalOpen = false;
    public $catatanText = '';

    public function mount()
    {
        $user = Auth::user();
        $this->headedUnits = Unit::where('kepala_unit_id', $user->id)->get();
        $this->isKepalaUnit = $this->headedUnits->count() > 0;

        $currentPeriode = Periode::where('is_current', true)->first();
        if ($currentPeriode) {
            $this->selectedPeriodeId = $currentPeriode->id;
        }
    }

    public function updatingSearch() { $this->resetPage(); }
    public function updatingFilterStatus() { $this->resetPage(); }
    public function updatingFilterTipe() { $this->resetPage(); }
    public function updatingSelectedPeriodeId() { $this->resetPage(); }
    
    public function updatingTipePengajuan() { $this->work_program_id = ''; }
    public function updatingUnitId() { $this->work_program_id = ''; }

    // ==========================================
    // FUNGSI: BUKA MODAL FORM
    // ==========================================
    public function openModal($id = null)
    {
        $this->resetValidation();
        $this->reset(['file_lampiran', 'file_lampiran_lama', 'work_program_id']);
        
        if ($id) {
            $submission = FundSubmission::findOrFail($id);
            
            // PROTEKSI KEPEMILIKAN: Pribadi vs Lembaga
            $isSubmitterPribadi = $submission->tipe_pengajuan === 'pribadi' && $submission->user_id === Auth::id();
            $isHeadOfUnit = $submission->tipe_pengajuan === 'lembaga' && $submission->unit_id && in_array($submission->unit_id, $this->headedUnits->pluck('id')->toArray());

            if (!$isSubmitterPribadi && (!$isHeadOfUnit)) {
                session()->flash('error', 'Akses Ditolak: Anda tidak lagi memiliki hak akses ke pengajuan ini karena bukan Pimpinan Unit terkait.');
                return;
            }
            
            if ($submission->status_pengajuan !== 'pending') {
                session()->flash('error', 'Pengajuan yang sudah diproses oleh keuangan tidak dapat diubah lagi.');
                return;
            }

            $this->submissionId = $submission->id;
            $this->tipe_pengajuan = $submission->tipe_pengajuan;
            $this->unit_id = $submission->tipe_pengajuan === 'lembaga' ? $submission->unit_id : '';
            $this->work_program_id = $submission->work_program_id ?? ''; 
            $this->nominal_total = round($submission->nominal_total); 
            $this->skema_pencairan = $submission->skema_pencairan ?? 'lumpsum'; 
            
            // Auto-detect usulan termin dari teks jika ada
            $usulanTermin = 2;
            if ($this->skema_pencairan === 'termin') {
                if (preg_match('/\[Usulan (\d+) Termin Pencairan\]/', $submission->keperluan, $matches)) {
                    $usulanTermin = (int) $matches[1];
                }
            }
            $this->jumlah_termin = $usulanTermin;

            // Bersihkan teks keperluan dari tag usulan agar tidak bertumpuk
            $this->keperluan = preg_replace('/\[Usulan \d+ Termin Pencairan\]\s*\n?/', '', $submission->keperluan);
            
            $this->file_lampiran_lama = $submission->file_lampiran;
        } else {
            $this->reset(['submissionId', 'unit_id', 'nominal_total', 'keperluan', 'work_program_id']);
            $this->tipe_pengajuan = 'pribadi';
            $this->skema_pencairan = 'lumpsum';
            $this->jumlah_termin = 2;
            
            if ($this->isKepalaUnit && $this->headedUnits->count() === 1) {
                $this->unit_id = $this->headedUnits->first()->id;
            }
        }
        
        $this->isModalOpen = true;
    }

    public function openCatatanModal($id)
    {
        $submission = FundSubmission::findOrFail($id);
        
        $isSubmitterPribadi = $submission->tipe_pengajuan === 'pribadi' && $submission->user_id === Auth::id();
        $isHeadOfUnit = $submission->tipe_pengajuan === 'lembaga' && $submission->unit_id && in_array($submission->unit_id, $this->headedUnits->pluck('id')->toArray());

        if (!$isSubmitterPribadi && !$isHeadOfUnit) {
            session()->flash('error', 'Akses Ditolak.');
            return;
        }

        $this->catatanText = $submission->catatan_verifikator;
        $this->isCatatanModalOpen = true;
    }

    // ==========================================
    // FUNGSI: SIMPAN DATA
    // ==========================================
    public function saveSubmission()
    {
        $periode = Periode::find($this->selectedPeriodeId);

        if (!$periode || $periode->status === 'closed') {
            session()->flash('error', 'Gagal menyimpan! Periode ini sudah dikunci atau tidak tersedia.');
            return;
        }

        $rules = [
            'tipe_pengajuan' => 'required|in:pribadi,lembaga',
            'work_program_id' => 'nullable|exists:work_programs,id',
            'nominal_total' => 'required|numeric|min:1000', 
            'skema_pencairan' => 'required|in:lumpsum,termin',
            'keperluan' => 'required|string|min:10',
            'file_lampiran' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:3072', 
        ];

        if ($this->tipe_pengajuan === 'lembaga') {
            $rules['unit_id'] = 'required|exists:units,id';
        }
        
        if ($this->skema_pencairan === 'termin') {
            $rules['jumlah_termin'] = 'required|integer|min:2|max:24';
        }

        $this->validate($rules);

        $finalUnitId = null;
        if ($this->tipe_pengajuan === 'pribadi') {
            $finalUnitId = Auth::user()->units->first()?->id; 
        } else {
            $finalUnitId = $this->unit_id; 
        }

        // Sisipkan info Usulan Termin ke dalam teks keperluan
        $finalKeperluan = $this->keperluan;
        if ($this->skema_pencairan === 'termin') {
            $finalKeperluan = "[Usulan " . $this->jumlah_termin . " Termin Pencairan]\n" . $this->keperluan;
        }

        $data = [
            'user_id' => Auth::id(), // ID pembuat asli akan tetap dicatat
            'unit_id' => $finalUnitId,
            'work_program_id' => $this->work_program_id ?: null, 
            'periode_id' => $periode->id, 
            'tipe_pengajuan' => $this->tipe_pengajuan,
            'nominal_total' => $this->nominal_total,
            'skema_pencairan' => $this->skema_pencairan,
            'keperluan' => $finalKeperluan, 
            'status_pengajuan' => 'pending', 
        ];

        if ($this->file_lampiran) {
            if ($this->submissionId && $this->file_lampiran_lama) {
                Storage::disk('public')->delete($this->file_lampiran_lama);
            }
            $data['file_lampiran'] = $this->file_lampiran->store('keuangan_files', 'public');
        }

        FundSubmission::updateOrCreate(['id' => $this->submissionId], $data);

        $this->isModalOpen = false;
        session()->flash('success', 'Pengajuan dana berhasil disimpan & dikirim ke Keuangan!');
    }

    public function confirmDelete($id)
    {
        $submission = FundSubmission::findOrFail($id);
        
        $isSubmitterPribadi = $submission->tipe_pengajuan === 'pribadi' && $submission->user_id === Auth::id();
        $isHeadOfUnit = $submission->tipe_pengajuan === 'lembaga' && $submission->unit_id && in_array($submission->unit_id, $this->headedUnits->pluck('id')->toArray());

        if (!$isSubmitterPribadi && !$isHeadOfUnit) {
            session()->flash('error', 'Akses Ditolak: Anda tidak lagi memiliki hak atas pengajuan ini.');
            return;
        }

        if ($submission->status_pengajuan !== 'pending') {
            session()->flash('error', 'Hanya pengajuan berstatus Pending yang dapat ditarik.');
            return;
        }

        $this->submissionToDeleteId = $id;
        $this->isDeleteModalOpen = true;
    }

    public function deleteSubmission()
    {
        if ($this->submissionToDeleteId) {
            $submission = FundSubmission::findOrFail($this->submissionToDeleteId);
            $submission->delete(); 
        }
        
        $this->isDeleteModalOpen = false;
        $this->submissionToDeleteId = null;
        session()->flash('success', 'Pengajuan berhasil ditarik/dibatalkan.');
    }

    // ==========================================
    // FUNGSI: READ DATA (Menarik Data Sesuai Otoritas Unit)
    // ==========================================
    public function with(): array
    {
        $allPeriodes = Periode::orderBy('tanggal_mulai', 'desc')->get();
        $selectedPeriode = Periode::find($this->selectedPeriodeId);

        $submissions = collect();
        $availableWorkPrograms = collect();

        if ($selectedPeriode) {
            $userId = Auth::id();
            $headedUnitIds = $this->headedUnits->pluck('id')->toArray();

            // LOGIKA PEMISAHAN KEPEMILIKAN: Pribadi vs Lembaga
            $query = FundSubmission::with(['unit', 'periode', 'workProgram', 'disbursements'])
                ->where('periode_id', $selectedPeriode->id)
                ->where(function ($q) use ($userId, $headedUnitIds) {
                    // 1. Pengajuan Pribadi -> Hanya milik sendiri
                    $q->where(function($q1) use ($userId) {
                        $q1->where('tipe_pengajuan', 'pribadi')
                           ->where('user_id', $userId);
                    })
                    // 2. Pengajuan Lembaga -> Milik unit yang sedang dipimpin
                    ->orWhere(function($q2) use ($headedUnitIds) {
                        $q2->where('tipe_pengajuan', 'lembaga')
                           ->whereIn('unit_id', $headedUnitIds);
                    });
                });

            if ($this->search) {
                $query->where('keperluan', 'like', '%' . $this->search . '%');
            }
            if ($this->filterStatus) {
                $query->where('status_pengajuan', $this->filterStatus); 
            }
            if ($this->filterTipe) {
                $query->where('tipe_pengajuan', $this->filterTipe);
            }

            $submissions = $query->latest()->paginate(10);

            $contextUnitId = $this->tipe_pengajuan === 'pribadi' ? Auth::user()->units->first()?->id : $this->unit_id;
            
            if ($contextUnitId) {
                $availableWorkPrograms = WorkProgram::where('unit_id', $contextUnitId)
                    ->where('periode_id', $selectedPeriode->id)
                    ->where('status', 'disetujui') 
                    ->get();
            }
        }

        return [
            'submissions' => $submissions,
            'allPeriodes' => $allPeriodes,
            'selectedPeriode' => $selectedPeriode,
            'availableWorkPrograms' => $availableWorkPrograms,
        ];
    }
}; ?>

<div class="space-y-6 relative max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 bg-white p-6 rounded-3xl border border-gray-200 shadow-sm">
        <div class="flex-1">
            <h1 class="text-2xl font-extrabold text-theme-text tracking-tight uppercase">Pengajuan Dana</h1>
            <p class="text-sm text-theme-muted mt-1">Ajukan anggaran untuk kebutuhan operasional pribadi maupun unit lembaga.</p>
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

            <div class="w-full sm:w-auto">
                <label class="block text-[10px] text-transparent hidden sm:block mb-1">-</label>
                <button wire:click="openModal" 
                        @if(!$selectedPeriode || $selectedPeriode->status === 'closed') disabled @endif 
                        class="w-full bg-primary hover:bg-primary-hover disabled:bg-gray-300 disabled:cursor-not-allowed text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-md transition-all flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    Buat Pengajuan
                </button>
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
                    <p class="text-sm text-amber-700 mt-1">Periode ini telah diarsipkan. Anda hanya dapat melihat data riwayat pengajuan tanpa bisa mengubahnya.</p>
                </div>
            </div>
        </div>
    @endif

    @if($selectedPeriode)
    <div class="bg-white p-4 rounded-2xl border border-gray-200 shadow-sm flex flex-col md:flex-row items-center gap-3">
        @if($isKepalaUnit)
        <div class="w-full md:w-auto flex-shrink-0">
            <select wire:model.live="filterTipe" class="block w-full border border-gray-300 bg-gray-50 rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-gray-900 font-medium transition-all">
                <option value="">Semua Tipe</option>
                <option value="pribadi">Pengajuan Pribadi</option>
                <option value="lembaga">Pengajuan Lembaga (Pimpinan)</option>
            </select>
        </div>
        @endif

        <div class="w-full md:w-auto flex-shrink-0">
            <select wire:model.live="filterStatus" class="block w-full border border-gray-300 bg-gray-50 rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-gray-900 font-medium transition-all">
                <option value="">Semua Status Keputusan</option>
                <option value="pending">⏳ Menunggu (Pending)</option>
                <option value="approved">✅ Disetujui (ACC)</option>
                <option value="rejected">❌ Ditolak (Revisi)</option>
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
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest w-40">Tanggal Pengajuan</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest">Detail Keperluan & Program Kerja</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest text-right w-64">Nominal Keuangan (Rp)</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest text-center w-36">Status Keuangan</th>
                        <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest text-right w-24">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($submissions as $item)
                        <tr class="hover:bg-gray-50/50 transition-colors group align-top" wire:key="submission-{{ $item->id }}">
                            
                            <td class="px-6 py-5">
                                <div class="text-sm font-bold text-gray-900">{{ $item->created_at->translatedFormat('d M Y') }}</div>
                                <div class="text-[10px] text-gray-500 mt-0.5">{{ $item->created_at->format('H:i') }} WIB</div>
                            </td>
                            
                            <td class="px-6 py-5">
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    @if($item->tipe_pengajuan === 'lembaga')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-blue-50 text-blue-600 border border-blue-200">
                                            Lembaga: {{ $item->unit ? ($item->unit->kode_unit ?? $item->unit->nama_unit) : '-' }}
                                        </span>
                                        @if($item->user_id !== Auth::id())
                                            <span class="text-[9px] text-gray-400 italic">Oleh: {{ $item->user->name ?? '-' }}</span>
                                        @endif
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-600 border border-emerald-200">
                                            Pribadi
                                        </span>
                                    @endif
                                    
                                    @if($item->workProgram)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-purple-50 text-purple-600 border border-purple-200" title="{{ $item->workProgram->nama_proker }}">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                                            Proker Terkait
                                        </span>
                                    @endif
                                </div>
                                
                                <p class="text-sm font-medium text-gray-800 leading-relaxed whitespace-pre-wrap">{{ preg_replace('/\[Usulan \d+ Termin Pencairan\]\s*\n?/', '', $item->keperluan) }}</p>
                                
                                @if($item->file_lampiran)
                                    <a href="{{ Storage::url($item->file_lampiran) }}" target="_blank" class="mt-2 inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-primary hover:text-primary-hover hover:underline transition-all">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                                        Buka Proposal / RAB
                                    </a>
                                @endif
                            </td>

                            <td class="px-6 py-5 text-right">
                                <div class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mb-0.5">Permintaan:</div>
                                <div class="text-sm font-extrabold text-gray-900 {{ $item->status_pengajuan === 'approved' && $item->nominal_disetujui != $item->nominal_total ? 'line-through text-gray-400' : '' }}">
                                    Rp {{ number_format($item->nominal_total, 0, ',', '.') }}
                                </div>
                                
                                <!-- Nominal Final Jika ACC -->
                                @if($item->status_pengajuan === 'approved')
                                    <div class="text-[10px] text-emerald-600 font-bold uppercase tracking-widest mt-2 mb-0.5">Di-ACC:</div>
                                    <div class="text-base font-extrabold text-emerald-700">
                                        Rp {{ number_format($item->nominal_disetujui, 0, ',', '.') }}
                                    </div>
                                    <div class="text-[9px] text-gray-500 mt-1 uppercase font-bold tracking-wider inline-block px-1.5 py-0.5 bg-gray-100 rounded border border-gray-200">
                                        {{ $item->skema_pencairan === 'termin' ? $item->disbursements->count().' Termin' : 'Lumpsum' }}
                                    </div>
                                @endif
                            </td>

                            <td class="px-6 py-5 text-center">
                                @if($item->status_pengajuan === 'pending')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-amber-50 text-amber-600 border border-amber-200 shadow-sm">
                                        <span class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-pulse mr-1.5"></span> Pending
                                    </span>
                                @elseif($item->status_pengajuan === 'approved')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200 shadow-sm">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Disetujui
                                    </span>
                                @elseif($item->status_pengajuan === 'rejected')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-red-50 text-red-700 border border-red-200 shadow-sm">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg> Ditolak
                                    </span>
                                @endif
                                
                                @if($item->catatan_verifikator)
                                    <button wire:click="openCatatanModal({{ $item->id }})" class="mt-2 text-[10px] flex items-center justify-center w-full gap-1 text-gray-500 hover:text-primary transition-colors hover:underline font-bold">
                                        Lihat Catatan
                                    </button>
                                @endif
                            </td>

                            <!-- Aksi -->
                            <td class="px-6 py-5 align-top text-right">
                                @if($item->status_pengajuan === 'pending' && $selectedPeriode->status !== 'closed')
                                    <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button wire:click="openModal({{ $item->id }})" class="text-gray-400 hover:text-primary transition-colors p-2 rounded-lg hover:bg-gray-100 border border-transparent hover:border-gray-200" title="Edit Pengajuan">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        </button>
                                        <button wire:click="confirmDelete({{ $item->id }})" class="text-gray-400 hover:text-red-500 transition-colors p-2 rounded-lg hover:bg-red-50 border border-transparent hover:border-red-100" title="Tarik/Batalkan">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </div>
                                @else
                                    <div class="flex flex-col items-end gap-1">
                                        <span class="text-[10px] text-gray-400 font-bold uppercase tracking-widest text-right">Terkunci</span>
                                        <span class="text-[9px] text-gray-400 text-right">Telah diverifikasi</span>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-16 text-center">
                                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4 border border-gray-200 shadow-inner">
                                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                </div>
                                <h4 class="text-sm font-bold text-gray-900 uppercase tracking-tight">Tidak Ada Data Pengajuan</h4>
                                <p class="text-xs text-gray-500 mt-1">Anda belum pernah mengajukan dana operasional pada filter/periode ini.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($submissions->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $submissions->links() }}
            </div>
        @endif
    </div>
    @endif

    <!-- ========================================== -->
    <!-- MODAL FORM (BUAT / EDIT) -->
    <!-- ========================================== -->
    @if($isModalOpen)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4 transition-opacity" style="pointer-events: auto;">
            <div class="bg-white rounded-3xl border border-gray-200 shadow-2xl w-full max-w-4xl flex flex-col max-h-[90vh] overflow-hidden" onclick="event.stopPropagation()">
                <!-- Header -->
                <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center z-10 shrink-0">
                    <h3 class="text-lg font-bold text-gray-900">
                        {{ $submissionId ? 'Edit Pengajuan Dana' : 'Buat Pengajuan Dana Baru' }}
                    </h3>
                    <button type="button" wire:click="$set('isModalOpen', false)" class="text-gray-400 hover:text-red-500 transition-colors bg-white p-2 rounded-lg border border-gray-200 shadow-sm hover:bg-red-50 hover:border-red-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <form wire:submit.prevent="saveSubmission" class="flex flex-col overflow-hidden">
                    <!-- Body Scrollable -->
                    <div class="p-6 overflow-y-auto custom-scrollbar flex-1">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            
                            <!-- KOLOM KIRI -->
                            <div class="space-y-6">
                                <h4 class="text-xs font-black text-gray-800 uppercase tracking-widest border-b border-gray-200 pb-2 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Identitas & Program
                                </h4>

                                <!-- Pilihan Tipe -->
                                @if($isKepalaUnit)
                                    <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-3 text-center">Ajukan Sebagai</label>
                                        <div class="grid grid-cols-2 gap-3">
                                            <label class="cursor-pointer">
                                                <input type="radio" wire:model.live="tipe_pengajuan" value="pribadi" class="hidden peer">
                                                <div class="p-3 text-center rounded-xl border border-gray-300 bg-white peer-checked:bg-emerald-600 peer-checked:border-emerald-600 peer-checked:text-white transition-all shadow-sm">
                                                    <span class="text-sm font-bold block mb-1">Dosen Pribadi</span>
                                                </div>
                                            </label>
                                            
                                            <label class="cursor-pointer">
                                                <input type="radio" wire:model.live="tipe_pengajuan" value="lembaga" class="hidden peer">
                                                <div class="p-3 text-center rounded-xl border border-gray-300 bg-white peer-checked:bg-blue-600 peer-checked:border-blue-600 peer-checked:text-white transition-all shadow-sm">
                                                    <span class="text-sm font-bold block mb-1">Unit Lembaga</span>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                @endif

                                <!-- Pilih Unit Lembaga -->
                                @if($tipe_pengajuan === 'lembaga')
                                    <div>
                                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Mewakili Unit/Lembaga <span class="text-red-500">*</span></label>
                                        <select wire:model.live="unit_id" class="block w-full border border-gray-300 bg-white rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-gray-900 font-medium shadow-sm">
                                            <option value="">-- Pilih Unit --</option>
                                            @foreach($headedUnits as $unit)
                                                <option value="{{ $unit->id }}">{{ $unit->nama_unit }}</option>
                                            @endforeach
                                        </select>
                                        @error('unit_id') <span class="text-xs text-red-500 mt-1 block font-bold">{{ $message }}</span> @enderror
                                    </div>
                                @endif

                                <!-- Pilih Proker -->
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Realisasi Program Kerja (Opsional)</label>
                                    <select wire:model="work_program_id" class="block w-full border border-gray-300 bg-white rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-gray-900 font-medium shadow-sm {{ $availableWorkPrograms->count() === 0 ? 'opacity-50' : '' }}">
                                        <option value="">-- Pengajuan Independen (Non-Proker) --</option>
                                        @foreach($availableWorkPrograms as $proker)
                                            <option value="{{ $proker->id }}">{{ $proker->nama_proker }}</option>
                                        @endforeach
                                    </select>
                                    <p class="text-[10px] text-gray-400 mt-1.5 font-medium leading-relaxed">Menampilkan daftar Program Kerja yang sebelumnya telah disahkan dan disetujui pada periode ini.</p>
                                    @error('work_program_id') <span class="text-xs text-red-500 mt-1 block font-bold">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <!-- KOLOM KANAN -->
                            <div class="space-y-6">
                                <h4 class="text-xs font-black text-gray-800 uppercase tracking-widest border-b border-gray-200 pb-2 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Rincian Anggaran
                                </h4>

                                <!-- Nominal & Skema Pencairan -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="col-span-2">
                                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Total Dana Permintaan <span class="text-red-500">*</span></label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 font-bold">Rp</span>
                                            <input type="number" wire:model="nominal_total" min="1000" class="block w-full pl-9 pr-3 py-2.5 border border-gray-300 bg-white rounded-xl shadow-sm focus:ring-primary focus:border-primary text-sm text-gray-900 font-bold" placeholder="5000000">
                                        </div>
                                        @error('nominal_total') <span class="text-xs text-red-500 mt-1 block font-bold">{{ $message }}</span> @enderror
                                    </div>

                                    <!-- Pilihan Lumpsum / Termin -->
                                    <div class="col-span-2 md:col-span-1">
                                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Request Skema Cair <span class="text-red-500">*</span></label>
                                        <select wire:model.live="skema_pencairan" class="block w-full border border-gray-300 bg-white rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-gray-900 font-bold shadow-sm">
                                            <option value="lumpsum">Lumpsum (Cair Sekaligus)</option>
                                            <option value="termin">Termin (Cair Bertahap)</option>
                                        </select>
                                        @error('skema_pencairan') <span class="text-xs text-red-500 mt-1 block font-bold">{{ $message }}</span> @enderror
                                    </div>

                                    <!-- Tambahan Fitur: Input Jumlah Termin Jika Memilih Termin -->
                                    @if($skema_pencairan === 'termin')
                                        <div class="col-span-2 md:col-span-1">
                                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Usulan Jumlah Termin <span class="text-red-500">*</span></label>
                                            <div class="relative">
                                                <input type="number" wire:model="jumlah_termin" min="2" max="12" class="block w-full border border-gray-300 bg-white rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-gray-900 font-bold shadow-sm" placeholder="2">
                                                <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-[10px] font-bold text-gray-400 uppercase tracking-widest">Kali</span>
                                            </div>
                                            @error('jumlah_termin') <span class="text-xs text-red-500 mt-1 block font-bold">{{ $message }}</span> @enderror
                                        </div>
                                    @endif
                                </div>

                                <!-- Keperluan -->
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Tujuan Peruntukan Dana <span class="text-red-500">*</span></label>
                                    <textarea wire:model="keperluan" rows="3" class="block w-full border border-gray-300 bg-white rounded-xl py-2.5 px-3 shadow-sm text-sm focus:ring-primary focus:border-primary text-gray-900" placeholder="Jelaskan secara rinci untuk keperluan apa dana ini diajukan..."></textarea>
                                    @error('keperluan') <span class="text-xs text-red-500 mt-1 block font-bold">{{ $message }}</span> @enderror
                                </div>

                                <!-- Lampiran -->
                                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 border-dashed">
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">File Lampiran Proposal / RAB (Opsional)</label>
                                    <input type="file" wire:model="file_lampiran" class="block w-full text-sm text-gray-700 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-[10px] file:font-bold file:uppercase file:tracking-wider file:bg-primary file:text-white hover:file:bg-primary-hover file:cursor-pointer file:transition-colors file:shadow-sm">
                                    <p class="text-[9px] text-gray-400 mt-1.5 font-medium">Maksimal 3MB (Format PDF, PNG, JPG).</p>
                                    <div wire:loading wire:target="file_lampiran" class="text-[10px] font-bold text-primary mt-2">Mengunggah file ke server...</div>
                                    @if($file_lampiran_lama && !$file_lampiran)
                                        <div class="text-[10px] font-bold text-emerald-700 mt-2 flex items-center gap-1 bg-emerald-50 px-2 py-1.5 border border-emerald-200 rounded-lg w-max shadow-sm">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                            Ada file tersimpan dari sebelumnya
                                        </div>
                                    @endif
                                    @error('file_lampiran') <span class="text-xs text-red-500 mt-2 block font-bold">{{ $message }}</span> @enderror
                                </div>
                            </div>

                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex justify-end gap-3 shrink-0">
                        <button type="button" wire:click="$set('isModalOpen', false)" class="px-5 py-2.5 text-sm font-bold text-gray-500 hover:text-gray-900 bg-white border border-gray-200 rounded-xl shadow-sm transition-colors">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" class="px-6 py-2.5 text-sm font-bold text-white bg-primary hover:bg-primary-hover rounded-xl shadow-md transition-all disabled:opacity-50 flex items-center gap-2">
                            <span wire:loading.remove wire:target="saveSubmission">Kirim Pengajuan ke Keuangan</span>
                            <span wire:loading wire:target="saveSubmission" class="flex items-center gap-2">
                                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Menyimpan Data...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- MODAL DELETE (KONFIRMASI HAPUS) -->
    <!-- ========================================== -->
    @if($isDeleteModalOpen)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4 transition-opacity" style="pointer-events: auto;">
            <div class="bg-white rounded-3xl border border-gray-200 shadow-2xl w-full max-w-sm overflow-hidden text-center p-6" onclick="event.stopPropagation()">
                <div class="w-16 h-16 bg-red-50 text-red-500 border border-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Batalkan Pengajuan?</h3>
                <p class="text-sm text-gray-500 mb-6">Data pengajuan ini akan ditarik permanen dari sistem. Anda yakin ingin melanjutkannya?</p>
                <div class="flex justify-center gap-3">
                    <button type="button" wire:click="$set('isDeleteModalOpen', false)" class="px-5 py-2.5 text-sm font-bold text-gray-500 hover:text-gray-900 bg-gray-50 rounded-xl border border-gray-200 transition-colors w-full">
                        Tidak, Kembali
                    </button>
                    <button type="button" wire:click="deleteSubmission" wire:loading.attr="disabled" class="px-5 py-2.5 text-sm font-bold text-white bg-red-600 hover:bg-red-700 rounded-xl shadow-md transition-all w-full flex items-center justify-center gap-2 disabled:opacity-50">
                        <span wire:loading.remove wire:target="deleteSubmission">Ya, Hapus</span>
                        <span wire:loading wire:target="deleteSubmission">Menghapus...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- MODAL LIHAT CATATAN -->
    <!-- ========================================== -->
    @if($isCatatanModalOpen)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4 transition-opacity" style="pointer-events: auto;">
            <div class="bg-white rounded-3xl border border-gray-200 shadow-2xl w-full max-w-sm overflow-hidden p-6" onclick="event.stopPropagation()">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-blue-50 text-blue-600 border border-blue-100 rounded-full flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900">Catatan Keuangan</h3>
                </div>
                
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 mb-6 shadow-inner">
                    <p class="text-sm text-gray-800 leading-relaxed whitespace-pre-wrap">{{ $catatanText }}</p>
                </div>

                <button type="button" wire:click="$set('isCatatanModalOpen', false)" class="w-full px-5 py-2.5 text-sm font-bold text-gray-500 hover:text-gray-900 bg-white shadow-sm rounded-xl border border-gray-200 transition-colors">
                    Tutup
                </button>
            </div>
        </div>
    @endif
</div>