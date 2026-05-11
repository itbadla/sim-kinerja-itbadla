<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\FundSubmission;
use App\Models\Unit;
use App\Models\Periode;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination, WithFileUploads;

    public $search = '';
    public $filterStatus = '';
    public $filterTipe = '';
    public $selectedPeriodeId = ''; // State untuk Dropdown Periode

    // ==========================================
    // STATE: MODAL FORM
    // ==========================================
    public $isModalOpen = false;
    public $submissionId = null;
    
    // Field Form
    public $tipe_pengajuan = 'pribadi';
    public $unit_id = ''; // Hanya diisi jika tipe = lembaga
    public $nominal = '';
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
        // Cek otoritas struktural user
        $user = Auth::user();
        $this->headedUnits = Unit::where('kepala_unit_id', $user->id)->get();
        $this->isKepalaUnit = $this->headedUnits->count() > 0;

        // Set dropdown ke periode yang aktif saat ini secara default
        $currentPeriode = Periode::where('is_current', true)->first();
        if ($currentPeriode) {
            $this->selectedPeriodeId = $currentPeriode->id;
        }
    }

    public function updatingSearch() { $this->resetPage(); }
    public function updatingFilterStatus() { $this->resetPage(); }
    public function updatingFilterTipe() { $this->resetPage(); }
    public function updatingSelectedPeriodeId() { $this->resetPage(); }

    // ==========================================
    // FUNGSI: BUKA MODAL FORM
    // ==========================================
    public function openModal($id = null)
    {
        $this->resetValidation();
        $this->reset(['file_lampiran', 'file_lampiran_lama']);
        
        if ($id) {
            $submission = FundSubmission::where('user_id', Auth::id())->findOrFail($id);
            
            // Mencegah edit jika sudah diproses
            if ($submission->status !== 'pending') {
                session()->flash('error', 'Pengajuan yang sudah diproses tidak dapat diubah.');
                return;
            }

            $this->submissionId = $submission->id;
            $this->tipe_pengajuan = $submission->tipe_pengajuan;
            $this->unit_id = $submission->tipe_pengajuan === 'lembaga' ? $submission->unit_id : '';
            $this->nominal = round($submission->nominal); // Hilangkan .00 untuk input number
            $this->keperluan = $submission->keperluan;
            $this->file_lampiran_lama = $submission->file_lampiran;
        } else {
            $this->reset(['submissionId', 'unit_id', 'nominal', 'keperluan']);
            $this->tipe_pengajuan = 'pribadi';
            
            // Set default unit_id jika dia kepala unit dan hanya memimpin 1 unit
            if ($this->isKepalaUnit && $this->headedUnits->count() === 1) {
                $this->unit_id = $this->headedUnits->first()->id;
            }
        }
        
        $this->isModalOpen = true;
    }

    // ==========================================
    // FUNGSI: BUKA MODAL CATATAN
    // ==========================================
    public function openCatatanModal($id)
    {
        $submission = FundSubmission::where('user_id', Auth::id())->findOrFail($id);
        $this->catatanText = $submission->catatan_verifikator;
        $this->isCatatanModalOpen = true;
    }

    // ==========================================
    // FUNGSI: SIMPAN DATA
    // ==========================================
    public function saveSubmission()
    {
        $periode = Periode::find($this->selectedPeriodeId);

        // Keamanan: Pastikan periode tersedia dan belum ditutup
        if (!$periode || $periode->status === 'closed') {
            session()->flash('error', 'Gagal menyimpan! Periode ini sudah dikunci atau tidak tersedia.');
            return;
        }

        $rules = [
            'tipe_pengajuan' => 'required|in:pribadi,lembaga',
            'nominal' => 'required|numeric|min:1000',
            'keperluan' => 'required|string|min:10',
            'file_lampiran' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:3072', // Maks 3MB
        ];

        // Validasi tambahan jika tipe_pengajuan = lembaga
        if ($this->tipe_pengajuan === 'lembaga') {
            $rules['unit_id'] = 'required|exists:units,id';
        }

        $this->validate($rules);

        // Penentuan Unit ID sebelum disimpan
        $finalUnitId = null;
        if ($this->tipe_pengajuan === 'pribadi') {
            $finalUnitId = Auth::user()->unit_id; // Masuk ke homebase
        } else {
            $finalUnitId = $this->unit_id; // Masuk ke unit yang dipilih
        }

        $data = [
            'user_id' => Auth::id(),
            'unit_id' => $finalUnitId,
            'periode_id' => $periode->id, // INJEKSI PERIODE BERDASARKAN DROPDOWN
            'tipe_pengajuan' => $this->tipe_pengajuan,
            'nominal' => $this->nominal,
            'keperluan' => $this->keperluan,
            'status' => 'pending', // Reset ke pending jika diedit
        ];

        // Handle File Upload
        if ($this->file_lampiran) {
            // Hapus file lama jika ada
            if ($this->submissionId && $this->file_lampiran_lama) {
                Storage::disk('public')->delete($this->file_lampiran_lama);
            }
            $data['file_lampiran'] = $this->file_lampiran->store('keuangan_files', 'public');
        }

        FundSubmission::updateOrCreate(['id' => $this->submissionId], $data);

        $this->isModalOpen = false;
        session()->flash('success', 'Pengajuan dana berhasil disimpan!');
    }

    // ==========================================
    // FUNGSI: HAPUS DATA
    // ==========================================
    public function confirmDelete($id)
    {
        $submission = FundSubmission::where('user_id', Auth::id())->findOrFail($id);
        
        if ($submission->status !== 'pending') {
            session()->flash('error', 'Hanya pengajuan berstatus Pending yang dapat dihapus.');
            return;
        }

        $this->submissionToDeleteId = $id;
        $this->isDeleteModalOpen = true;
    }

    public function deleteSubmission()
    {
        if ($this->submissionToDeleteId) {
            $submission = FundSubmission::findOrFail($this->submissionToDeleteId);
            $submission->delete(); // Ini Soft Delete sesuai migrasi
        }
        
        $this->isDeleteModalOpen = false;
        $this->submissionToDeleteId = null;
        session()->flash('success', 'Pengajuan berhasil dihapus/dibatalkan.');
    }

    // ==========================================
    // FUNGSI: READ DATA
    // ==========================================
    public function with(): array
    {
        $allPeriodes = Periode::orderBy('tanggal_mulai', 'desc')->get();
        $selectedPeriode = Periode::find($this->selectedPeriodeId);

        $submissions = collect();

        // Hanya load data jika ada periode terpilih
        if ($selectedPeriode) {
            $query = FundSubmission::with(['unit', 'periode'])
                ->where('user_id', Auth::id())
                ->where('periode_id', $selectedPeriode->id);

            // Filter Pencarian
            if ($this->search) {
                $query->where('keperluan', 'like', '%' . $this->search . '%');
            }

            // Filter Status
            if ($this->filterStatus) {
                $query->where('status', $this->filterStatus);
            }

            // Filter Tipe
            if ($this->filterTipe) {
                $query->where('tipe_pengajuan', $this->filterTipe);
            }

            $submissions = $query->latest()->paginate(10);
        }

        return [
            'submissions' => $submissions,
            'allPeriodes' => $allPeriodes,
            'selectedPeriode' => $selectedPeriode,
        ];
    }
}; ?>

<div class="space-y-6 relative">
    <!-- Header Halaman & Dropdown Periode -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
        <div class="flex-1">
            <h1 class="text-2xl font-extrabold text-theme-text tracking-tight">Pengajuan Dana</h1>
            <p class="text-sm text-theme-muted mt-1">Ajukan anggaran untuk kebutuhan operasional pribadi maupun lembaga.</p>
        </div>
        
        <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto">
            <!-- Dropdown Filter Periode -->
            <div class="w-full sm:w-64">
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Pilih Periode Kinerja</label>
                <select wire:model.live="selectedPeriodeId" class="w-full border-gray-300 bg-gray-50 rounded-xl text-sm font-bold text-gray-900 focus:ring-primary focus:border-primary shadow-sm cursor-pointer">
                    <option value="">-- Pilih Periode --</option>
                    @foreach($allPeriodes as $p)
                        <option value="{{ $p->id }}">
                            {{ $p->nama_periode }} 
                            @if($p->is_current) (Aktif) @endif
                            @if($p->status === 'closed') (Arsip) @endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="w-full sm:w-auto">
                <label class="block text-[10px] text-transparent hidden sm:block mb-1">-</label>
                <button wire:click="openModal" 
                        @if(!$selectedPeriode || $selectedPeriode->status === 'closed') disabled @endif 
                        class="w-full bg-primary hover:bg-primary-hover disabled:bg-gray-300 disabled:cursor-not-allowed text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-sm transition-all flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    Buat Pengajuan
                </button>
            </div>
        </div>
    </div>

    <!-- Alert Sukses/Error -->
    @if (session()->has('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 text-red-600 dark:text-red-400 px-4 py-3 rounded-xl text-sm font-medium flex items-center gap-2 shadow-sm">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            {{ session('error') }}
        </div>
    @endif
    @if (session()->has('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)" class="bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-emerald-600 dark:text-emerald-400 px-4 py-3 rounded-xl text-sm font-medium flex items-center gap-2 shadow-sm">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            {{ session('success') }}
        </div>
    @endif

    <!-- Warning Jika Belum Pilih Periode / Terkunci -->
    @if(!$selectedPeriode)
        <div class="bg-amber-50 border border-amber-200 text-amber-700 rounded-xl p-4 text-sm font-medium flex items-center gap-3 shadow-sm">
            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            Sistem terkunci. Belum ada periode yang dipilih atau periode aktif belum tersedia. Harap hubungi Administrator.
        </div>
    @elseif($selectedPeriode->status === 'closed')
        <div class="bg-amber-50 border-l-4 border-amber-500 p-4 rounded-xl shadow-sm">
            <div class="flex items-center">
                <svg class="h-6 w-6 text-amber-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                <div>
                    <h3 class="text-sm font-bold text-amber-800">Periode {{ $selectedPeriode->nama_periode }} Ditutup</h3>
                    <p class="text-sm text-amber-700 mt-1">Periode ini telah diarsipkan. Anda hanya dapat melihat data pengajuan tanpa bisa menambah atau mengubahnya.</p>
                </div>
            </div>
        </div>
    @endif

    @if($selectedPeriode)
    <!-- Kotak Filter & Pencarian -->
    <div class="bg-theme-surface p-4 rounded-2xl border border-theme-border shadow-sm flex flex-col md:flex-row items-center gap-3">
        <!-- Filter Tipe Pengajuan (Jika Kepala Unit) -->
        @if($isKepalaUnit)
        <div class="w-full md:w-auto flex-shrink-0">
            <select wire:model.live="filterTipe" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text font-medium">
                <option value="">Semua Tipe</option>
                <option value="pribadi">Pengajuan Pribadi</option>
                <option value="lembaga">Pengajuan Lembaga</option>
            </select>
        </div>
        @endif

        <!-- Filter Status -->
        <div class="w-full md:w-auto flex-shrink-0">
            <select wire:model.live="filterStatus" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text font-medium">
                <option value="">Semua Status</option>
                <option value="pending">Pending / Menunggu</option>
                <option value="approved">Disetujui</option>
                <option value="rejected">Ditolak</option>
            </select>
        </div>

        <!-- Pencarian -->
        <div class="relative w-full">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-theme-muted">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </span>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari rincian keperluan..." class="block w-full pl-10 pr-3 py-2.5 border border-theme-border bg-theme-body rounded-xl focus:ring-primary focus:border-primary text-sm text-theme-text transition-all">
        </div>
    </div>

    <!-- Tabel Data -->
    <div class="bg-theme-surface border border-theme-border rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[800px]">
                <thead class="bg-theme-body/50 border-b border-theme-border">
                    <tr>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest w-40">Tanggal</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest">Detail Keperluan</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest text-right">Nominal (Rp)</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest text-center w-32">Status</th>
                        <th class="px-6 py-4 text-xs font-bold text-theme-muted uppercase tracking-widest text-right w-24">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-theme-border">
                    @forelse($submissions as $item)
                        <tr class="hover:bg-theme-body/30 transition-colors group" wire:key="submission-{{ $item->id }}">
                            
                            <!-- Tanggal -->
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm font-bold text-theme-text">{{ $item->created_at->translatedFormat('d M Y') }}</div>
                                <div class="text-[10px] text-theme-muted mt-0.5">{{ $item->created_at->format('H:i') }} WIB</div>
                            </td>
                            
                            <!-- Keperluan & Unit -->
                            <td class="px-6 py-4 align-top">
                                <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                    @if($item->tipe_pengajuan === 'lembaga')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-blue-50 text-blue-600 border border-blue-200 dark:bg-blue-900/30 dark:border-blue-800/50 dark:text-blue-400">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                            Lembaga: {{ $item->unit ? ($item->unit->kode_unit ?? $item->unit->nama_unit) : '-' }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-600 border border-emerald-200 dark:bg-emerald-900/30 dark:border-emerald-800/50 dark:text-emerald-400">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                            Pribadi
                                        </span>
                                    @endif
                                </div>
                                
                                <p class="text-sm font-medium text-theme-text">{{ $item->keperluan }}</p>
                                
                                @if($item->file_lampiran)
                                    <a href="{{ Storage::url($item->file_lampiran) }}" target="_blank" class="mt-2 inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-theme-muted hover:text-primary transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                                        Lihat Lampiran
                                    </a>
                                @endif
                            </td>

                            <!-- Nominal -->
                            <td class="px-6 py-4 align-top text-right">
                                <div class="text-sm font-extrabold text-theme-text">
                                    Rp {{ number_format($item->nominal, 0, ',', '.') }}
                                </div>
                            </td>

                            <!-- Status -->
                            <td class="px-6 py-4 align-top text-center">
                                @if($item->status === 'pending')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-amber-50 text-amber-600 border border-amber-200 dark:bg-amber-500/10 dark:border-amber-500/20">Pending</span>
                                @elseif($item->status === 'approved')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-600 border border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/20">Disetujui</span>
                                @elseif($item->status === 'rejected')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-red-50 text-red-600 border border-red-200 dark:bg-red-500/10 dark:border-red-500/20">Ditolak</span>
                                @endif
                                
                                @if($item->catatan_verifikator)
                                    <button wire:click="openCatatanModal({{ $item->id }})" class="mt-2 text-[10px] flex items-center justify-center w-full gap-1 text-theme-muted hover:text-primary transition-colors hover:underline">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
                                        Lihat Catatan
                                    </button>
                                @endif
                            </td>

                            <!-- Aksi -->
                            <td class="px-6 py-4 align-top text-right">
                                @if($item->status === 'pending' && $selectedPeriode->status !== 'closed')
                                    <div class="flex items-center justify-end gap-1 sm:opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button wire:click="openModal({{ $item->id }})" class="text-theme-muted hover:text-primary transition-colors p-2 rounded-lg hover:bg-theme-body border border-transparent hover:border-theme-border" title="Edit">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        </button>
                                        <button wire:click="confirmDelete({{ $item->id }})" class="text-theme-muted hover:text-red-500 transition-colors p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-500/10 border border-transparent hover:border-red-200" title="Hapus">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </div>
                                @else
                                    <div class="flex justify-end pr-2">
                                        <svg class="w-5 h-5 text-theme-muted opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="Terkunci. Sudah diproses atau periode berakhir."><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-16 text-center">
                                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-theme-body mb-4 border border-theme-border shadow-inner">
                                    <svg class="w-10 h-10 text-theme-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                </div>
                                <h4 class="text-base font-bold text-theme-text uppercase tracking-tight">Belum Ada Pengajuan</h4>
                                <p class="text-sm text-theme-muted mt-1">Anda belum memiliki riwayat pengajuan dana pada periode ini.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Paginasi -->
        @if($submissions->hasPages())
            <div class="px-6 py-4 border-t border-theme-border bg-theme-surface">
                {{ $submissions->links() }}
            </div>
        @endif
    </div>
    @endif

    <!-- ========================================== -->
    <!-- MODAL FORM (BUAT / EDIT) -->
    <!-- ========================================== -->
    @if($isModalOpen)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-theme-text/20 backdrop-blur-sm p-4 transition-opacity" style="pointer-events: auto;">
            <div class="bg-theme-surface rounded-2xl border border-theme-border shadow-2xl w-full max-w-2xl flex flex-col max-h-[90vh] overflow-hidden" onclick="event.stopPropagation()">
                <!-- Header Sticky -->
                <div class="px-6 py-4 border-b border-theme-border bg-theme-body/50 flex justify-between items-center z-10 shrink-0">
                    <h3 class="text-lg font-bold text-theme-text">
                        {{ $submissionId ? 'Edit Pengajuan Dana' : 'Buat Pengajuan Baru' }}
                        <span class="block text-xs font-normal text-gray-500 mt-0.5">Periode: {{ $selectedPeriode->nama_periode }}</span>
                    </h3>
                    <button type="button" wire:click="$set('isModalOpen', false)" class="text-theme-muted hover:text-red-500 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <form wire:submit.prevent="saveSubmission" class="flex flex-col overflow-hidden">
                    <!-- Body Scrollable -->
                    <div class="p-6 overflow-y-auto custom-scrollbar space-y-6 flex-1">
                        
                        <!-- Pilihan Tipe Pengajuan (Hanya jika Kepala Unit) -->
                        @if($isKepalaUnit)
                            <div class="bg-theme-body p-4 rounded-xl border border-theme-border">
                                <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-3 text-center">Tipe Pengajuan</label>
                                <div class="grid grid-cols-2 gap-3">
                                    <label class="cursor-pointer">
                                        <input type="radio" wire:model.live="tipe_pengajuan" value="pribadi" class="hidden peer">
                                        <div class="p-3 text-center rounded-xl border border-theme-border bg-theme-surface peer-checked:bg-emerald-600 peer-checked:text-white transition-all shadow-sm peer-checked:shadow-emerald-600/20">
                                            <span class="text-sm font-bold block mb-1">Pribadi</span>
                                            <span class="text-[10px] opacity-80 block font-medium leading-tight">Keperluan individual</span>
                                        </div>
                                    </label>
                                    
                                    <label class="cursor-pointer">
                                        <input type="radio" wire:model.live="tipe_pengajuan" value="lembaga" class="hidden peer">
                                        <div class="p-3 text-center rounded-xl border border-theme-border bg-theme-surface peer-checked:bg-blue-600 peer-checked:text-white transition-all shadow-sm peer-checked:shadow-blue-600/20">
                                            <span class="text-sm font-bold block mb-1">Lembaga</span>
                                            <span class="text-[10px] opacity-80 block font-medium leading-tight">Operasional unit/lembaga</span>
                                        </div>
                                    </label>
                                </div>
                                @error('tipe_pengajuan') <span class="text-[10px] text-red-500 mt-2 block text-center">{{ $message }}</span> @enderror
                            </div>
                        @endif

                        <!-- Pilih Unit (Muncul jika Tipe Lembaga) -->
                        @if($tipe_pengajuan === 'lembaga')
                            <div>
                                <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Atas Nama Unit / Lembaga <span class="text-red-500">*</span></label>
                                <select wire:model="unit_id" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text font-medium">
                                    <option value="">-- Pilih Unit --</option>
                                    @foreach($headedUnits as $unit)
                                        <option value="{{ $unit->id }}">{{ $unit->nama_unit }}</option>
                                    @endforeach
                                </select>
                                @error('unit_id') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        @endif

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Nominal -->
                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Nominal Pengajuan (Rp) <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-theme-muted font-bold">Rp</span>
                                    <input type="number" wire:model="nominal" min="1000" class="block w-full pl-9 pr-3 py-2.5 border border-theme-border bg-theme-body rounded-xl focus:ring-primary focus:border-primary text-sm text-theme-text font-bold" placeholder="500000">
                                </div>
                                @error('nominal') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <!-- File Lampiran -->
                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Lampiran <span class="text-theme-muted font-normal normal-case">(Opsional, Maks 3MB)</span></label>
                                <input type="file" wire:model="file_lampiran" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2 px-3 text-sm text-theme-text file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-[10px] file:font-bold file:uppercase file:tracking-wider file:bg-primary file:text-white hover:file:bg-primary-hover file:cursor-pointer file:transition-colors">
                                <div wire:loading wire:target="file_lampiran" class="text-[10px] font-bold text-primary mt-1 flex items-center gap-1">
                                    <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    Memproses file...
                                </div>
                                @if($file_lampiran_lama && !$file_lampiran)
                                    <div class="text-[10px] font-bold text-emerald-600 mt-1.5 flex items-center gap-1 bg-emerald-50 dark:bg-emerald-500/10 px-2 py-1 rounded w-max">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> 
                                        Ada file tersimpan
                                    </div>
                                @endif
                                @error('file_lampiran') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <!-- Keperluan -->
                        <div>
                            <label class="block text-[10px] font-bold text-theme-muted uppercase tracking-wider mb-1.5">Rincian Keperluan <span class="text-red-500">*</span></label>
                            <textarea wire:model="keperluan" rows="4" class="block w-full border border-theme-border bg-theme-body rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-theme-text" placeholder="Jelaskan secara rinci peruntukan dana ini..."></textarea>
                            @error('keperluan') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                    </div>
                    
                    <!-- Footer Sticky -->
                    <div class="px-6 py-4 border-t border-theme-border bg-theme-body/50 flex justify-end gap-3 shrink-0">
                        <button type="button" wire:click="$set('isModalOpen', false)" class="px-4 py-2 text-sm font-bold text-theme-muted hover:text-theme-text transition-colors">
                            Batal
                        </button>
                        
                        <button type="submit" wire:loading.attr="disabled" class="px-6 py-2 text-sm font-bold text-white bg-primary hover:bg-primary-hover rounded-xl shadow-md transition-all flex items-center gap-2 disabled:opacity-50">
                            <span wire:loading.remove wire:target="saveSubmission">Simpan & Kirim</span>
                            <span wire:loading wire:target="saveSubmission">Memproses...</span>
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
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-theme-text/20 backdrop-blur-sm p-4 transition-opacity" style="pointer-events: auto;">
            <div class="bg-theme-surface rounded-2xl border border-theme-border shadow-2xl w-full max-w-sm overflow-hidden text-center p-6" onclick="event.stopPropagation()">
                <div class="w-16 h-16 bg-red-100 dark:bg-red-500/20 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <h3 class="text-xl font-bold text-theme-text mb-2">Batalkan Pengajuan?</h3>
                <p class="text-sm text-theme-muted mb-6">Data pengajuan ini akan dihapus dari sistem. Anda yakin ingin melanjutkannya?</p>
                <div class="flex justify-center gap-3">
                    <button type="button" wire:click="$set('isDeleteModalOpen', false)" class="px-5 py-2.5 text-sm font-bold text-theme-muted hover:text-theme-text bg-theme-body rounded-xl border border-theme-border transition-colors w-full">
                        Tidak, Kembali
                    </button>
                    <button type="button" wire:click="deleteSubmission" wire:loading.attr="disabled" class="px-5 py-2.5 text-sm font-bold text-white bg-red-500 hover:bg-red-600 rounded-xl shadow-md transition-all w-full flex items-center justify-center gap-2 disabled:opacity-50">
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
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-theme-text/20 backdrop-blur-sm p-4 transition-opacity" style="pointer-events: auto;">
            <div class="bg-theme-surface rounded-2xl border border-theme-border shadow-2xl w-full max-w-sm overflow-hidden p-6" onclick="event.stopPropagation()">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-blue-100 dark:bg-blue-500/20 text-blue-600 rounded-full flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold text-theme-text">Catatan Verifikator</h3>
                </div>
                
                <div class="bg-theme-body p-4 rounded-xl border border-theme-border mb-6">
                    <p class="text-sm text-theme-text leading-relaxed whitespace-pre-wrap">{{ $catatanText }}</p>
                </div>

                <button type="button" wire:click="$set('isCatatanModalOpen', false)" class="w-full px-5 py-2.5 text-sm font-bold text-theme-muted hover:text-theme-text bg-theme-body rounded-xl border border-theme-border transition-colors">
                    Tutup
                </button>
            </div>
        </div>
    @endif
</div>