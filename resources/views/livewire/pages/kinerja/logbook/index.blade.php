<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\Logbook;
use App\Models\Periode;
use App\Models\WorkProgram;
use Illuminate\Support\Facades\Storage;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination, WithFileUploads;

    public $search = '';
    public $selectedPeriodeId = ''; 
    
    // State Modal
    public $isCreateModalOpen = false;
    public $isEditModalOpen = false;
    public $isDeleteModalOpen = false;

    // Form Properties
    public $logbookId;
    public $unit_id = ''; // TAMBAHAN: Menyimpan konteks unit saat ini
    public $tanggal;
    public $jam_mulai;
    public $jam_selesai;
    public $kategori = 'tugas_utama';
    public $work_program_id = '';
    public $deskripsi_aktivitas;
    public $output;
    public $link_bukti;
    public $file_bukti;
    public $existing_file; 

    public function mount()
    {
        $currentPeriode = Periode::where('is_current', true)->first();
        
        if ($currentPeriode) {
            $this->selectedPeriodeId = $currentPeriode->id;
        }

        // Set default unit_id ke unit pertama yang dimiliki user
        $userUnits = auth()->user()->units;
        if ($userUnits->count() > 0) {
            $this->unit_id = $userUnits->first()->id;
        }
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingSelectedPeriodeId()
    {
        $this->resetPage();
        $this->resetForm();
    }

    // Jika user mengganti konteks unit, reset pilihan prokernya agar tidak salah
    public function updatingUnitId()
    {
        $this->work_program_id = '';
    }

    public function resetForm()
    {
        $this->logbookId = null;
        $this->tanggal = date('Y-m-d');
        $this->jam_mulai = '';
        $this->jam_selesai = '';
        $this->kategori = 'tugas_utama';
        $this->work_program_id = '';
        $this->deskripsi_aktivitas = '';
        $this->output = '';
        $this->link_bukti = '';
        $this->file_bukti = null;
        $this->existing_file = null;
        $this->resetValidation();

        // Kembalikan unit ke default jika form ditutup
        $userUnits = auth()->user()->units;
        if ($userUnits->count() > 0) {
            $this->unit_id = $userUnits->first()->id;
        }
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->isCreateModalOpen = true;
    }

    public function openEditModal($id)
    {
        $this->resetValidation();
        $logbook = Logbook::findOrFail($id);

        if ($logbook->status === 'approved') {
            session()->flash('error', 'Logbook yang sudah disetujui tidak dapat diubah.');
            return;
        }

        $this->logbookId = $logbook->id;
        $this->unit_id = $logbook->unit_id; // Set form ke unit asal saat logbook dibuat
        $this->tanggal = $logbook->tanggal->format('Y-m-d');
        $this->jam_mulai = $logbook->jam_mulai->format('H:i');
        $this->jam_selesai = $logbook->jam_selesai->format('H:i');
        $this->kategori = $logbook->kategori;
        $this->work_program_id = $logbook->work_program_id;
        $this->deskripsi_aktivitas = $logbook->deskripsi_aktivitas;
        $this->output = $logbook->output;
        $this->link_bukti = $logbook->link_bukti;
        $this->existing_file = $logbook->file_bukti;

        $this->isEditModalOpen = true;
    }

    public function confirmDelete($id)
    {
        $logbook = Logbook::findOrFail($id);
        
        if ($logbook->status === 'approved') {
            session()->flash('error', 'Logbook yang sudah disetujui tidak dapat dihapus.');
            return;
        }

        $this->logbookId = $id;
        $this->isDeleteModalOpen = true;
    }

    public function store()
    {
        $periode = Periode::find($this->selectedPeriodeId);

        if (!$periode || $periode->status === 'closed') {
            session()->flash('error', 'Gagal menyimpan! Periode ini sudah dikunci atau tidak tersedia.');
            return;
        }

        $validated = $this->validate([
            'unit_id' => 'required|exists:units,id',
            'tanggal' => 'required|date',
            'jam_mulai' => 'required',
            'jam_selesai' => 'required|after:jam_mulai',
            'kategori' => 'required|in:tugas_utama,tugas_tambahan',
            'work_program_id' => 'nullable|exists:work_programs,id',
            'deskripsi_aktivitas' => 'required|string|max:1000',
            'output' => 'nullable|string|max:255',
            'link_bukti' => 'nullable|url',
            'file_bukti' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $filePath = null;
        if ($this->file_bukti) {
            $filePath = $this->file_bukti->store('logbook_evidences', 'public');
        }

        Logbook::create([
            'user_id' => auth()->id(),
            'unit_id' => $this->unit_id, // Gunakan Unit yang DIPILIH pengguna
            'periode_id' => $periode->id,
            'tanggal' => $this->tanggal,
            'jam_mulai' => $this->jam_mulai,
            'jam_selesai' => $this->jam_selesai,
            'kategori' => $this->kategori,
            'work_program_id' => $this->work_program_id ?: null,
            'deskripsi_aktivitas' => $this->deskripsi_aktivitas,
            'output' => $this->output,
            'link_bukti' => $this->link_bukti,
            'file_bukti' => $filePath,
            'status' => 'pending', 
        ]);

        $this->closeModal();
        session()->flash('message', 'Logbook harian berhasil dicatat.');
    }

    public function update()
    {
        $logbook = Logbook::where('id', $this->logbookId)->where('user_id', auth()->id())->firstOrFail();

        $validated = $this->validate([
            'unit_id' => 'required|exists:units,id',
            'tanggal' => 'required|date',
            'jam_mulai' => 'required',
            'jam_selesai' => 'required|after:jam_mulai',
            'kategori' => 'required|in:tugas_utama,tugas_tambahan',
            'work_program_id' => 'nullable|exists:work_programs,id',
            'deskripsi_aktivitas' => 'required|string|max:1000',
            'output' => 'nullable|string|max:255',
            'link_bukti' => 'nullable|url',
            'file_bukti' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $filePath = $logbook->file_bukti;
        
        if ($this->file_bukti) {
            if ($filePath && Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
            }
            $filePath = $this->file_bukti->store('logbook_evidences', 'public');
        }

        $logbook->update([
            'unit_id' => $this->unit_id,
            'tanggal' => $this->tanggal,
            'jam_mulai' => $this->jam_mulai,
            'jam_selesai' => $this->jam_selesai,
            'kategori' => $this->kategori,
            'work_program_id' => $this->work_program_id ?: null,
            'deskripsi_aktivitas' => $this->deskripsi_aktivitas,
            'output' => $this->output,
            'link_bukti' => $this->link_bukti,
            'file_bukti' => $filePath,
            'status' => 'pending', 
        ]);

        $this->closeModal();
        session()->flash('message', 'Perubahan logbook berhasil disimpan.');
    }

    public function destroy()
    {
        $logbook = Logbook::where('id', $this->logbookId)->where('user_id', auth()->id())->firstOrFail();
        
        if ($logbook->file_bukti && Storage::disk('public')->exists($logbook->file_bukti)) {
            Storage::disk('public')->delete($logbook->file_bukti);
        }

        $logbook->delete();
        
        $this->closeModal();
        session()->flash('message', 'Data logbook dihapus.');
    }

    public function closeModal()
    {
        $this->isCreateModalOpen = false;
        $this->isEditModalOpen = false;
        $this->isDeleteModalOpen = false;
        $this->resetForm();
    }

    public function with(): array
    {
        $allPeriodes = Periode::orderBy('tanggal_mulai', 'desc')->get();
        $selectedPeriode = Periode::find($this->selectedPeriodeId);
        $userUnits = auth()->user()->units; // Ambil semua unit user
        
        $logbooks = collect(); 
        $workPrograms = collect();

        if ($selectedPeriode) {
            $query = Logbook::with(['workProgram', 'verifikator'])
                            ->where('user_id', auth()->id())
                            ->where('periode_id', $selectedPeriode->id);

            if ($this->search) {
                $query->where(function($q) {
                    $q->where('deskripsi_aktivitas', 'like', '%' . $this->search . '%')
                      ->orWhere('kategori', 'like', '%' . $this->search . '%');
                });
            }

            $logbooks = $query->orderBy('tanggal', 'desc')->orderBy('jam_mulai', 'desc')->paginate(15);
            
            // List Proker bergantung pada Unit ID yang DIPILIH user di dropdown
            if ($this->unit_id) {
                $workPrograms = WorkProgram::where('unit_id', $this->unit_id)
                                           ->where('periode_id', $selectedPeriode->id)
                                           ->where('status', 'disetujui')
                                           ->get();
            }
        }

        return [
            'allPeriodes' => $allPeriodes,
            'selectedPeriode' => $selectedPeriode,
            'logbooks' => $logbooks,
            'availableWorkPrograms' => $workPrograms,
            'userUnits' => $userUnits, // Kirim daftar unit ke tampilan
        ];
    }
}; ?>

<div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
    
    <!-- Peringatan Jika Tidak Ada Tahun Anggaran Aktif -->
    @if(!$selectedPeriode)
        <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-xl shadow-sm mb-6">
            <div class="flex items-center">
                <svg class="h-6 w-6 text-red-500 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <div>
                    <h3 class="text-sm font-bold text-red-800">Sistem Terkunci / Periode Belum Dipilih</h3>
                    <p class="text-sm text-red-700 mt-1">Belum ada periode kinerja yang dipilih atau periode aktif belum tersedia. Harap pilih periode di dropdown atau hubungi Administrator.</p>
                </div>
            </div>
        </div>
    @elseif($selectedPeriode->status === 'closed')
        <div class="bg-amber-50 border-l-4 border-amber-500 p-4 rounded-xl shadow-sm mb-6">
            <div class="flex items-center">
                <svg class="h-6 w-6 text-amber-500 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                <div>
                    <h3 class="text-sm font-bold text-amber-800">Periode {{ $selectedPeriode->nama_periode }} Ditutup</h3>
                    <p class="text-sm text-amber-700 mt-1">Periode ini telah diarsipkan. Anda hanya dapat melihat data kinerja tanpa bisa menambah atau mengubahnya.</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Header Halaman & Filter -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
        <div class="flex-1">
            <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Logbook Kinerja Harian</h1>
            <p class="text-sm text-gray-500 mt-1">Catat dan pantau histori aktivitas harian Anda.</p>
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
                <button wire:click="openCreateModal" 
                        @if(!$selectedPeriode || $selectedPeriode->status === 'closed') disabled @endif 
                        class="w-full bg-primary hover:bg-primary-hover disabled:bg-gray-300 disabled:cursor-not-allowed text-white px-5 py-2 rounded-xl font-bold text-sm shadow-sm transition-all flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Tulis Aktivitas
                </button>
            </div>
        </div>
    </div>

    <!-- Notifikasi Sukses/Error -->
    @if (session()->has('message'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-sm font-medium shadow-sm">
            {{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm font-medium shadow-sm">
            {{ session('error') }}
        </div>
    @endif

    @if($selectedPeriode)
        <!-- Kotak Pencarian -->
        <div class="relative w-full md:w-96">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </span>
            <input type="text" wire:model.live="search" class="block w-full pl-10 pr-3 py-2 border border-gray-200 bg-white shadow-sm rounded-xl focus:ring-primary focus:border-primary text-sm text-gray-900 transition-all" placeholder="Cari aktivitas atau kategori...">
        </div>

        <!-- Daftar Logbook (Grid Cards) -->
        <div class="grid grid-cols-1 gap-4">
            @forelse($logbooks as $log)
                <div wire:key="log-{{ $log->id }}" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 hover:border-primary/30 transition-all flex flex-col md:flex-row gap-5">
                    
                    <!-- Sisi Kiri: Waktu & Status -->
                    <div class="flex flex-row md:flex-col justify-between items-start md:w-48 shrink-0 border-b md:border-b-0 md:border-r border-gray-100 pb-4 md:pb-0 md:pr-4">
                        <div>
                            <p class="text-sm font-bold text-gray-900">{{ $log->tanggal->translatedFormat('l, d M Y') }}</p>
                            <p class="text-xs text-gray-500 font-medium mt-1">
                                <svg class="w-3.5 h-3.5 inline mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                {{ $log->jam_mulai->format('H:i') }} - {{ $log->jam_selesai->format('H:i') }}
                            </p>
                        </div>
                        
                        <div class="mt-0 md:mt-4">
                            @php
                                $statusStyles = [
                                    'draft' => 'bg-gray-100 text-gray-700 border-gray-200',
                                    'pending' => 'bg-blue-50 text-blue-700 border-blue-200',
                                    'approved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    'rejected' => 'bg-red-50 text-red-700 border-red-200',
                                ];
                                $statusLabels = [
                                    'draft' => 'Draf',
                                    'pending' => 'Menunggu Verifikasi',
                                    'approved' => 'Disetujui',
                                    'rejected' => 'Perlu Revisi',
                                ];
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider border {{ $statusStyles[$log->status] }}">
                                {{ $statusLabels[$log->status] }}
                            </span>
                        </div>
                    </div>

                    <!-- Sisi Tengah: Konten Aktivitas -->
                    <div class="flex-1 space-y-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded {{ $log->kategori === 'tugas_utama' ? 'bg-primary/10 text-primary' : 'bg-purple-100 text-purple-700' }} uppercase border border-current/20">
                                    {{ str_replace('_', ' ', $log->kategori) }}
                                </span>
                                
                                <!-- Tambahan Tampilan Unit -->
                                @if($log->unit)
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-gray-100 text-gray-600 border border-gray-200 uppercase">
                                        {{ $log->unit->nama_unit }}
                                    </span>
                                @endif
                                
                                @if($log->workProgram)
                                    <span class="text-[10px] font-semibold text-gray-500 bg-gray-100 px-2 py-0.5 rounded truncate max-w-[200px]" title="{{ $log->workProgram->nama_proker }}">
                                        Proker: {{ $log->workProgram->nama_proker }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-sm text-gray-800 leading-relaxed font-medium">{{ $log->deskripsi_aktivitas }}</p>
                        </div>

                        <!-- Bukti / Output -->
                        <div class="flex flex-wrap items-center gap-3 pt-2">
                            @if($log->output)
                                <span class="text-xs text-gray-600 bg-gray-50 px-2 py-1 rounded border border-gray-100">
                                    <span class="font-semibold">Output:</span> {{ $log->output }}
                                </span>
                            @endif
                            
                            @if($log->file_bukti)
                                <a href="{{ Storage::url($log->file_bukti) }}" target="_blank" class="text-xs text-primary hover:underline flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                                    Lampiran Bukti
                                </a>
                            @endif

                            @if($log->link_bukti)
                                <a href="{{ $log->link_bukti }}" target="_blank" class="text-xs text-blue-600 hover:underline flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                                    Tautan Luar
                                </a>
                            @endif
                        </div>

                        <!-- Catatan Verifikator -->
                        @if($log->catatan_verifikator && $log->status !== 'draft')
                            <div class="mt-2 p-3 bg-red-50 border border-red-100 rounded-lg">
                                <p class="text-xs font-bold text-red-800 mb-1">Catatan Verifikator ({{ $log->verifikator->name ?? 'Atasan' }}):</p>
                                <p class="text-xs text-red-700">{{ $log->catatan_verifikator }}</p>
                            </div>
                        @endif
                    </div>

                    <!-- Sisi Kanan: Aksi -->
                    <div class="flex items-start md:justify-end gap-2 shrink-0 border-t md:border-t-0 md:border-l border-gray-100 pt-4 md:pt-0 md:pl-4">
                        @if($selectedPeriode->status !== 'closed' && $log->status !== 'approved')
                            <button wire:click="openEditModal({{ $log->id }})" class="p-2 text-gray-400 hover:text-primary hover:bg-primary/5 rounded-lg transition-colors" title="Ubah Logbook">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                            </button>
                            <button wire:click="confirmDelete({{ $log->id }})" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors" title="Hapus Logbook">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        @elseif($log->status === 'approved')
                            <span class="text-xs text-emerald-600 font-bold flex items-center gap-1 mt-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                Terkunci
                            </span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="py-16 text-center bg-white rounded-3xl border border-dashed border-gray-300 text-gray-400">
                    <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <p class="text-sm font-medium">Belum ada aktivitas yang dicatat pada periode ini.</p>
                </div>
            @endforelse
        </div>

        <div class="mt-4">
            {{ $logbooks->links() }}
        </div>
    @endif

    <!-- MODAL FORM LOGBOOK -->
    @if($isCreateModalOpen || $isEditModalOpen)
        <div class="fixed inset-0 z-[100] flex items-center justify-center p-4 overflow-y-auto" x-data>
            <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity" wire:click="closeModal"></div>
            
            <div class="relative bg-white w-full max-w-3xl rounded-2xl shadow-xl overflow-hidden my-auto">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-gray-900">
                        {{ $isEditModalOpen ? 'Ubah Aktivitas' : 'Catat Aktivitas Baru' }}
                        <span class="block text-xs font-normal text-gray-500 mt-0.5">Periode: {{ $selectedPeriode->nama_periode }}</span>
                    </h3>
                    <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-red-500 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <form wire:submit.prevent="{{ $isEditModalOpen ? 'update' : 'store' }}" class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-5">
                        
                        <!-- Pilihan Konteks Unit (Khusus untuk Rangkap Jabatan) -->
                        @if($userUnits->count() > 1)
                            <div class="md:col-span-3 bg-blue-50 border border-blue-200 p-4 rounded-xl mb-2">
                                <label class="block text-xs font-bold text-blue-800 uppercase mb-2 flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                    Konteks Pelaporan (Unit Anda) <span class="text-red-500">*</span>
                                </label>
                                <select wire:model.live="unit_id" class="w-full border-blue-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 bg-white shadow-sm font-bold text-blue-900">
                                    @foreach($userUnits as $u)
                                        <option value="{{ $u->id }}">Sebagai personil di: {{ $u->nama_unit }}</option>
                                    @endforeach
                                </select>
                                <p class="text-[10px] text-blue-600 mt-1.5">Pilihan ini menentukan siapa Atasan (Verifikator) yang akan memeriksa logbook Anda.</p>
                                @error('unit_id') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        @endif

                        <div class="md:col-span-1 space-y-5">
                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Tanggal</label>
                                <input type="date" wire:model="tanggal" class="w-full border-gray-300 rounded-xl text-sm focus:ring-primary focus:border-primary">
                                @error('tanggal') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Mulai</label>
                                    <input type="time" wire:model="jam_mulai" class="w-full border-gray-300 rounded-xl text-sm focus:ring-primary focus:border-primary">
                                    @error('jam_mulai') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Selesai</label>
                                    <input type="time" wire:model="jam_selesai" class="w-full border-gray-300 rounded-xl text-sm focus:ring-primary focus:border-primary">
                                    @error('jam_selesai') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Kategori Tugas</label>
                                <select wire:model="kategori" class="w-full border-gray-300 rounded-xl text-sm focus:ring-primary focus:border-primary">
                                    <option value="tugas_utama">Tugas Utama</option>
                                    <option value="tugas_tambahan">Tugas Tambahan</option>
                                </select>
                                @error('kategori') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="md:col-span-2 space-y-5">
                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Terkait Program Kerja (Opsional)</label>
                                <select wire:model="work_program_id" class="w-full border-gray-300 rounded-xl text-sm focus:ring-primary focus:border-primary {{ $availableWorkPrograms->count() === 0 ? 'bg-gray-100 text-gray-400' : '' }}">
                                    <option value="">-- Tidak Terkait Proker Khusus --</option>
                                    @foreach($availableWorkPrograms ?? [] as $wp)
                                        <option value="{{ $wp->id }}">{{ $wp->nama_proker }}</option>
                                    @endforeach
                                </select>
                                <p class="text-[10px] text-gray-500 mt-1">Hanya menampilkan Proker yang telah disetujui pada unit dan periode terpilih.</p>
                                @error('work_program_id') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Uraian Aktivitas <span class="text-red-500">*</span></label>
                                <textarea wire:model="deskripsi_aktivitas" rows="3" placeholder="Jelaskan apa yang Anda kerjakan hari ini..." class="w-full border-gray-300 rounded-xl text-sm focus:ring-primary focus:border-primary"></textarea>
                                @error('deskripsi_aktivitas') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Output / Hasil (Opsional)</label>
                                <input type="text" wire:model="output" placeholder="Misal: 1 Dokumen Modul" class="w-full border-gray-300 rounded-xl text-sm focus:ring-primary focus:border-primary">
                                @error('output') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 p-4 bg-gray-50 rounded-xl border border-gray-200">
                        <div>
                            <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Upload Bukti Dokumen/Foto</label>
                            <input type="file" wire:model="file_bukti" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 transition-all cursor-pointer">
                            <p class="text-[10px] text-gray-500 mt-1">Format: PDF, JPG, PNG. Maks: 5MB</p>
                            @error('file_bukti') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                            
                            @if($existing_file && !$file_bukti)
                                <div class="mt-2 flex items-center gap-2 text-xs text-emerald-600">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    File saat ini sudah terunggah
                                </div>
                            @endif
                            <div wire:loading wire:target="file_bukti" class="text-[10px] text-blue-500 mt-1 font-bold animate-pulse">Mengunggah file...</div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Atau Tautan Bukti Eksternal</label>
                            <input type="url" wire:model="link_bukti" placeholder="https://drive.google.com/..." class="w-full border-gray-300 rounded-xl text-sm focus:ring-primary focus:border-primary">
                            <p class="text-[10px] text-gray-500 mt-1">Gunakan link Drive jika file besar.</p>
                            @error('link_bukti') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-6 mt-2 border-t border-gray-100">
                        <button type="button" wire:click="closeModal" class="px-5 py-2 text-sm font-bold text-gray-500 hover:text-gray-900 transition-colors">Batal</button>
                        <button type="submit" class="bg-primary hover:bg-primary-hover text-white px-6 py-2 rounded-xl font-bold text-sm shadow-sm transition-all flex items-center gap-2">
                            <span wire:loading.remove wire:target="{{ $isEditModalOpen ? 'update' : 'store' }}">
                                {{ $isEditModalOpen ? 'Simpan Perubahan' : 'Kirim Logbook' }}
                            </span>
                            <span wire:loading wire:target="{{ $isEditModalOpen ? 'update' : 'store' }}">
                                Memproses...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- MODAL KONFIRMASI HAPUS -->
    @if($isDeleteModalOpen)
        <div class="fixed inset-0 z-[150] flex items-center justify-center p-4" x-data>
            <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity" wire:click="closeModal"></div>
            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 text-center my-auto">
                <div class="w-16 h-16 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Hapus Aktivitas?</h3>
                <p class="text-sm text-gray-500 mb-6">Tindakan ini tidak dapat dibatalkan. File bukti yang terkait juga akan dihapus dari server.</p>
                <div class="flex gap-3">
                    <button type="button" wire:click="closeModal" class="flex-1 py-2.5 text-sm font-bold text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors">Batal</button>
                    <button type="button" wire:click="destroy" class="flex-1 py-2.5 text-sm font-bold text-white bg-red-500 rounded-xl shadow-sm hover:bg-red-600 transition-colors">Ya, Hapus</button>
                </div>
            </div>
        </div>
    @endif
</div>