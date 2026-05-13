<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\BkdActivity;
use App\Models\BkdDocument;
use App\Models\Periode;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination, WithFileUploads;

    public $search = '';
    public $selectedPeriodeId = '';
    public $activeTab = 'pendidikan';

    // =====================================
    // STATE MODAL UTAMA (FORM KEGIATAN)
    // =====================================
    public $isModalOpen = false;
    public $activityId = null;
    
    public $judul_kegiatan = '';
    public $rubrik_kegiatan_id = '';
    public $tanggal_mulai = '';
    public $tanggal_selesai = '';
    public $sks_beban = 0;
    public $deskripsi = '';

    // Field untuk input dokumen baru
    public $nama_dokumen = '';
    public $tautan_luar = '';
    public $file_dokumen;
    
    // Menampung dokumen yang sudah ada (saat mode edit)
    public $existing_documents = [];

    // =====================================
    // STATE MODAL GOOGLE SCHOLAR (2-STEP)
    // =====================================
    public $isScholarModalOpen = false;
    public $stepScholar = 1; // 1: Input ID, 2: Pilih Artikel
    public $scholar_input = '';
    public $scholar_results = [];
    public $selected_scholar_items = []; // Menyimpan key (md5 judul) artikel yang dicentang

    public function mount()
    {
        $currentPeriode = Periode::where('is_current', true)->first();
        if ($currentPeriode) {
            $this->selectedPeriodeId = $currentPeriode->id;
        }
    }

    public function updatingSearch() { $this->resetPage(); }
    public function updatingSelectedPeriodeId() { $this->resetPage(); }

    public function setTab($tabName)
    {
        $this->activeTab = $tabName;
        $this->resetPage();
    }

    // =====================================
    // MANAJEMEN MODAL & FORM UTAMA
    // =====================================
    public function resetForm()
    {
        $this->reset([
            'activityId', 'judul_kegiatan', 'rubrik_kegiatan_id', 
            'tanggal_mulai', 'tanggal_selesai', 'sks_beban', 'deskripsi',
            'nama_dokumen', 'tautan_luar', 'file_dokumen', 'existing_documents'
        ]);
        $this->resetValidation();
    }

    public function openModal($id = null)
    {
        $this->resetForm();
        if ($id) {
            $activity = BkdActivity::with('documents')->where('user_id', Auth::id())->findOrFail($id);
            $this->activityId = $activity->id;
            $this->judul_kegiatan = $activity->judul_kegiatan;
            $this->rubrik_kegiatan_id = $activity->rubrik_kegiatan_id;
            $this->tanggal_mulai = $activity->tanggal_mulai ? $activity->tanggal_mulai->format('Y-m-d') : '';
            $this->tanggal_selesai = $activity->tanggal_selesai ? $activity->tanggal_selesai->format('Y-m-d') : '';
            $this->sks_beban = $activity->sks_beban;
            $this->deskripsi = $activity->deskripsi;
            $this->existing_documents = $activity->documents; 
        }
        $this->isModalOpen = true;
    }

    public function closeModal()
    {
        $this->isModalOpen = false;
        $this->resetForm();
    }

    public function saveActivity()
    {
        $this->validate([
            'judul_kegiatan' => 'required|string|max:255',
            'sks_beban' => 'required|numeric|min:0|max:20',
            'tanggal_mulai' => 'nullable|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
            'nama_dokumen' => 'nullable|required_with:file_dokumen,tautan_luar|string|max:255',
            'tautan_luar' => 'nullable|url',
            'file_dokumen' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $activity = BkdActivity::updateOrCreate(
            ['id' => $this->activityId],
            [
                'user_id' => Auth::id(),
                'periode_id' => $this->selectedPeriodeId,
                'kategori_tridharma' => $this->activeTab,
                'judul_kegiatan' => $this->judul_kegiatan,
                'rubrik_kegiatan_id' => $this->rubrik_kegiatan_id,
                'tanggal_mulai' => $this->tanggal_mulai ?: null,
                'tanggal_selesai' => $this->tanggal_selesai ?: null,
                'sks_beban' => $this->sks_beban,
                'deskripsi' => $this->deskripsi,
                'status_internal' => 'draft',
            ]
        );

        if ($this->nama_dokumen && ($this->file_dokumen || $this->tautan_luar)) {
            $filePath = $this->file_dokumen ? $this->file_dokumen->store('bkd_docs', 'public') : null;
            
            BkdDocument::create([
                'bkd_activity_id' => $activity->id,
                'nama_dokumen' => $this->nama_dokumen,
                'file_path' => $filePath,
                'tautan_luar' => $this->tautan_luar,
            ]);
        }

        $this->closeModal();
        session()->flash('message', 'Aktivitas Tridharma berhasil disimpan.');
    }

    public function deleteDocument($docId)
    {
        $doc = BkdDocument::whereHas('activity', function($q) {
            $q->where('user_id', Auth::id());
        })->findOrFail($docId);

        if ($doc->file_path && Storage::disk('public')->exists($doc->file_path)) {
            Storage::disk('public')->delete($doc->file_path);
        }
        
        $doc->delete();
        $this->existing_documents = BkdDocument::where('bkd_activity_id', $this->activityId)->get();
        session()->flash('message', 'Dokumen berhasil dihapus.');
    }

    public function deleteActivity($id)
    {
        $activity = BkdActivity::where('user_id', Auth::id())->findOrFail($id);
        if ($activity->sync_status === 'synced') {
            session()->flash('error', 'Aktivitas yang sudah disinkronkan dengan SISTER tidak dapat dihapus dari lokal.');
            return;
        }

        foreach($activity->documents as $doc) {
            if ($doc->file_path && Storage::disk('public')->exists($doc->file_path)) {
                Storage::disk('public')->delete($doc->file_path);
            }
        }

        $activity->delete(); 
        session()->flash('message', 'Aktivitas beserta dokumennya berhasil dihapus.');
    }

    // =====================================
    // MANAJEMEN GOOGLE SCHOLAR (2-STEP)
    // =====================================
    public function openScholarModal()
    {
        if (!$this->selectedPeriodeId) {
            session()->flash('error', 'Pilih periode semester terlebih dahulu.');
            return;
        }
        
        $this->reset(['scholar_input', 'scholar_results', 'selected_scholar_items']);
        $this->stepScholar = 1;
        $this->resetValidation();
        
        $this->isScholarModalOpen = true;
    }

    public function closeScholarModal()
    {
        $this->isScholarModalOpen = false;
        $this->reset(['scholar_input', 'scholar_results', 'selected_scholar_items', 'stepScholar']);
    }

    // TAHAP 1: Ambil Data dan Filter
    public function fetchScholarData()
    {
        $this->validate([
            'scholar_input' => 'required|string|min:5'
        ], [
            'scholar_input.required' => 'URL atau ID Google Scholar wajib diisi.'
        ]);

        $user = Auth::user();
        $input = trim($this->scholar_input);
        $scholarId = $input;

        if (preg_match('/user=([a-zA-Z0-9_-]+)/', $input, $matches)) {
            $scholarId = $matches[1];
        }

        // Ambil rentang tahun dari Periode yang dipilih untuk rekomendasi (Auto-check)
        $periode = Periode::find($this->selectedPeriodeId);
        $startYear = $periode->tanggal_mulai ? (int) $periode->tanggal_mulai->format('Y') : date('Y');
        $endYear = $periode->tanggal_selesai ? (int) $periode->tanggal_selesai->format('Y') : date('Y');

        try {
            $url = "https://scholar.google.co.id/citations?hl=id&user={$scholarId}&cstart=0&pagesize=50"; // Ambil 50

            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9',
            ])->get($url);

            if ($response->status() === 429) {
                session()->flash('error', 'Gagal menarik data: IP Server kampus kita sementara diblokir oleh Google. Silakan coba lagi nanti.');
                return;
            }

            if ($response->failed()) {
                session()->flash('error', 'Gagal menarik data. Pastikan URL/ID Google Scholar valid.');
                return;
            }

            $html = $response->body();
            libxml_use_internal_errors(true); 
            $dom = new \DOMDocument();
            $dom->loadHTML($html);
            libxml_clear_errors();

            $xpath = new \DOMXPath($dom);
            $articlesNode = $xpath->query('//tr[@class="gsc_a_tr"]');

            if ($articlesNode->length === 0) {
                session()->flash('error', 'Gagal menemukan artikel. Pastikan ID valid dan profil bersifat Publik.');
                return;
            }

            $results = [];
            $autoSelected = [];

            foreach ($articlesNode as $node) {
                $titleNodes = $xpath->query('.//a[@class="gsc_a_at"]', $node);
                $judul = $titleNodes->length > 0 ? $titleNodes->item(0)->nodeValue : null;
                $link = $titleNodes->length > 0 ? 'https://scholar.google.com' . $titleNodes->item(0)->getAttribute('href') : '';

                $yearNodes = $xpath->query('.//span[contains(@class, "gsc_a_h")]', $node);
                $tahun = $yearNodes->length > 0 ? trim($yearNodes->item(0)->nodeValue) : date('Y');
                if (!is_numeric($tahun)) $tahun = date('Y');
                $tahun = (int) $tahun;

                if (!$judul) continue;

                $key = md5($judul);
                $exists = BkdActivity::where('user_id', $user->id)->where('judul_kegiatan', $judul)->exists();
                $inPeriod = ($tahun >= $startYear && $tahun <= $endYear);

                $results[] = [
                    'key' => $key,
                    'judul' => $judul,
                    'link' => $link,
                    'tahun' => $tahun,
                    'exists' => $exists,
                    'in_period' => $inPeriod,
                    'scholar_id' => $scholarId
                ];

                // Auto-centang jika sesuai tahun periode dan belum pernah diimpor
                if ($inPeriod && !$exists) {
                    $autoSelected[] = $key;
                }
            }

            // Kelompokkan berdasarkan tahun agar rapi
            usort($results, fn($a, $b) => $b['tahun'] <=> $a['tahun']);
            
            $this->scholar_results = $results;
            $this->selected_scholar_items = $autoSelected;
            $this->stepScholar = 2; // Pindah ke Tahap Pemilihan

        } catch (\Exception $e) {
            session()->flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    // TAHAP 2: Simpan Artikel yang Dipilih
    public function saveSelectedScholar()
    {
        if (empty($this->selected_scholar_items)) {
            session()->flash('error', 'Tidak ada artikel yang dipilih untuk diimpor.');
            return;
        }

        $user = Auth::user();
        $importedCount = 0;

        foreach ($this->scholar_results as $res) {
            if (in_array($res['key'], $this->selected_scholar_items) && !$res['exists']) {
                // 1. Simpan Kegiatan
                $activity = BkdActivity::create([
                    'user_id' => $user->id,
                    'periode_id' => $this->selectedPeriodeId,
                    'kategori_tridharma' => $this->activeTab,
                    'judul_kegiatan' => $res['judul'],
                    'rubrik_kegiatan_id' => '120', 
                    'tanggal_mulai' => $res['tahun'] . '-01-01',
                    'tanggal_selesai' => $res['tahun'] . '-12-31',
                    'sks_beban' => 2.0, 
                    'deskripsi' => "Diimpor otomatis dari Google Scholar Web Scraper | ID: " . $res['scholar_id'],
                    'status_internal' => 'draft',
                    'sync_status' => 'un-synced',
                ]);

                // 2. Simpan Tautan
                if ($res['link']) {
                    BkdDocument::create([
                        'bkd_activity_id' => $activity->id,
                        'nama_dokumen' => 'Tautan Publikasi Google Scholar',
                        'tautan_luar' => $res['link']
                    ]);
                }
                $importedCount++;
            }
        }

        $this->closeScholarModal();
        $this->setTab($this->activeTab);
        session()->flash('message', "Berhasil menarik {$importedCount} artikel baru langsung dari Google Scholar Anda.");
    }

    // =====================================
    // MENGAMBIL DATA UTAMA
    // =====================================
    public function with(): array
    {
        $allPeriodes = Periode::orderBy('tanggal_mulai', 'desc')->get();
        $selectedPeriode = Periode::find($this->selectedPeriodeId);
        
        $activities = collect();
        $rekap = [];

        if ($selectedPeriode) {
            $query = BkdActivity::with(['documents'])
                ->where('user_id', Auth::id())
                ->where('periode_id', $selectedPeriode->id)
                ->where('kategori_tridharma', $this->activeTab);

            if ($this->search) {
                $query->where('judul_kegiatan', 'like', '%' . $this->search . '%');
            }

            $activities = $query->latest()->paginate(10);

            $rekap = BkdActivity::where('user_id', Auth::id())
                ->where('periode_id', $selectedPeriode->id)
                ->selectRaw('kategori_tridharma, count(*) as total')
                ->groupBy('kategori_tridharma')
                ->pluck('total', 'kategori_tridharma')
                ->toArray();
        }

        return [
            'activities' => $activities,
            'allPeriodes' => $allPeriodes,
            'rekap' => $rekap,
            'selectedPeriodeName' => $selectedPeriode->nama_periode ?? ''
        ];
    }
}; ?>

<div class="space-y-6 relative max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    
    <!-- Header Halaman -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 bg-white p-6 rounded-3xl border border-gray-200 shadow-sm">
        <div class="flex-1">
            <h1 class="text-2xl font-extrabold text-theme-text tracking-tight uppercase">Kinerja Tridharma (BKD)</h1>
            <p class="text-sm text-theme-muted mt-1">Kelola portofolio Tridharma Anda dan integrasikan dokumen pendukung (Siakad/G-Scholar).</p>
        </div>
        
        <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto">
            <div class="w-full sm:w-64">
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Pilih Semester Kinerja</label>
                <select wire:model.live="selectedPeriodeId" class="w-full border-gray-300 bg-gray-50 rounded-xl text-sm font-bold text-gray-900 focus:ring-primary focus:border-primary shadow-sm cursor-pointer">
                    <option value="">-- Pilih Periode --</option>
                    @foreach($allPeriodes as $p)
                        <option value="{{ $p->id }}">{{ $p->nama_periode }} @if($p->is_current) (Aktif) @endif</option>
                    @endforeach
                </select>
            </div>

            <div class="w-full sm:w-auto">
                <label class="block text-[10px] text-transparent hidden sm:block mb-1">-</label>
                <button wire:click="openModal" class="w-full bg-primary hover:bg-primary-hover text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-md transition-all flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Tambah Kegiatan
                </button>
            </div>
        </div>
    </div>

    <!-- Alert Notifikasi -->
    @if (session()->has('message'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-sm font-medium shadow-sm mb-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                {{ session('message') }}
            </div>
            <button @click="show = false" class="text-emerald-500 hover:text-emerald-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
    @endif
    @if (session()->has('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)" class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm font-medium shadow-sm mb-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                {{ session('error') }}
            </div>
            <button @click="show = false" class="text-red-500 hover:text-red-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
    @endif

    @if($selectedPeriodeId)
        <!-- Navigasi Tabs -->
        <div class="flex overflow-x-auto bg-white rounded-2xl border border-gray-200 shadow-sm p-1 hide-scrollbar">
            @php
                $tabs = [
                    'pendidikan' => 'Pendidikan',
                    'penelitian' => 'Penelitian',
                    'pengabdian' => 'Pengabdian',
                    'penunjang' => 'Penunjang'
                ];
            @endphp
            
            @foreach($tabs as $key => $label)
                <button wire:click="setTab('{{ $key }}')" class="flex-1 whitespace-nowrap px-4 py-3 text-sm font-bold rounded-xl transition-all flex items-center justify-center gap-2 {{ $activeTab === $key ? 'bg-primary text-white shadow-md' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50' }}">
                    {{ $label }}
                    <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 text-[10px] rounded-full {{ $activeTab === $key ? 'bg-white/20 text-white' : 'bg-gray-200 text-gray-600' }}">
                        {{ $rekap[$key] ?? 0 }}
                    </span>
                </button>
            @endforeach
        </div>

        <!-- Toolbar Pencarian & Fitur Integrasi API/Scraping -->
        <div class="bg-theme-surface p-4 rounded-2xl border border-theme-border shadow-sm flex flex-col md:flex-row items-center justify-between gap-3">
            <div class="relative w-full md:w-96">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-theme-muted">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari judul kegiatan..." class="block w-full pl-10 pr-3 py-2.5 border border-theme-border bg-theme-body rounded-xl focus:ring-primary focus:border-primary text-sm text-theme-text transition-all">
            </div>

            <div class="flex flex-wrap gap-2 w-full md:w-auto">
                
                @if($activeTab === 'pendidikan')
                    <button onclick="alert('Fitur integrasi SIAKAD sedang dalam tahap pengembangan API. Segera hadir.')" class="flex-1 md:flex-none flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-600 hover:text-white rounded-xl text-sm font-bold transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        Tarik SIAKAD
                    </button>
                @endif

                @if($activeTab === 'penelitian' || $activeTab === 'pengabdian')
                    <button wire:click="openScholarModal" class="flex-1 md:flex-none flex items-center justify-center gap-2 px-4 py-2.5 bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-600 hover:text-white rounded-xl text-sm font-bold transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                        Tarik G-Scholar
                    </button>
                @endif
                
                <button onclick="alert('Sinkronisasi SISTER membutuhkan pengaturan Web Service di server pusat. Silakan hubungi admin.')" class="flex-1 md:flex-none flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-600 hover:text-white rounded-xl text-sm font-bold transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    Tarik SISTER
                </button>
            </div>
        </div>

        <!-- Tabel Konten -->
        <div class="bg-white border border-gray-200 rounded-3xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[900px]">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest w-1/2">Informasi Kegiatan & Dokumen</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest text-center w-24">SKS</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest text-center w-32">SISTER</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest text-center w-32">Verifikasi</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest text-right w-24">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($activities as $item)
                            <tr class="hover:bg-gray-50/50 transition-colors align-top">
                                <td class="px-6 py-5">
                                    <div class="text-sm font-bold text-gray-900 mb-1 leading-tight">{{ $item->judul_kegiatan }}</div>
                                    <div class="flex items-center gap-2 mb-3">
                                        <span class="text-[10px] text-gray-500 font-bold uppercase tracking-wider bg-gray-100 px-2 py-0.5 rounded border border-gray-200">
                                            Rubrik: {{ $item->rubrik_kegiatan_id ?: 'Belum Dipetakan' }}
                                        </span>
                                        @if($item->tanggal_mulai)
                                            <span class="text-[10px] text-gray-500 font-bold uppercase tracking-wider bg-gray-100 px-2 py-0.5 rounded border border-gray-200 flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                {{ $item->tanggal_mulai->format('d/m/y') }} 
                                                @if($item->tanggal_selesai) - {{ $item->tanggal_selesai->format('d/m/y') }} @endif
                                            </span>
                                        @endif
                                    </div>

                                    <!-- Render Daftar Dokumen / Link -->
                                    @if($item->documents->count() > 0)
                                        <div class="space-y-1.5 mt-2">
                                            @foreach($item->documents as $doc)
                                                <div class="flex items-center gap-2 bg-blue-50/50 border border-blue-100 rounded-lg px-2.5 py-1.5 w-max max-w-full">
                                                    @if($doc->file_path)
                                                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                                        <a href="{{ Storage::url($doc->file_path) }}" target="_blank" class="text-xs font-semibold text-emerald-700 hover:underline truncate">{{ $doc->nama_dokumen }}</a>
                                                    @elseif($doc->tautan_luar)
                                                        <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                                                        <a href="{{ $doc->tautan_luar }}" target="_blank" class="text-xs font-semibold text-indigo-700 hover:underline truncate">{{ $doc->nama_dokumen }}</a>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-5 text-center">
                                    <span class="font-extrabold text-gray-800 text-lg">{{ $item->sks_beban }}</span>
                                </td>
                                <td class="px-6 py-5 text-center">
                                    @if($item->sync_status === 'synced')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-green-50 text-green-700 text-[10px] font-bold uppercase tracking-wider border border-green-200 shadow-sm">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> SISTER
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-amber-50 text-amber-600 text-[10px] font-bold uppercase tracking-wider border border-amber-200 shadow-sm">
                                            Lokal
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-5 text-center">
                                    @if($item->status_internal === 'draft')
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 bg-gray-100 px-2 py-1 rounded border border-gray-200">Draft</span>
                                    @elseif($item->status_internal === 'pending')
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-blue-600 bg-blue-50 px-2 py-1 rounded border border-blue-200">Menunggu</span>
                                    @elseif($item->status_internal === 'approved')
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 bg-emerald-50 px-2 py-1 rounded border border-emerald-200">Disetujui</span>
                                    @elseif($item->status_internal === 'rejected')
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-red-600 bg-red-50 px-2 py-1 rounded border border-red-200">Revisi</span>
                                    @endif
                                </td>
                                <td class="px-6 py-5 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button wire:click="openModal({{ $item->id }})" class="p-2 text-gray-400 hover:text-primary transition-colors bg-gray-50 hover:bg-primary/10 rounded-lg border border-transparent hover:border-primary/20" title="Edit Kegiatan & Dokumen">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                        </button>
                                        <button onclick="confirm('Yakin ingin menghapus?') || event.stopImmediatePropagation()" wire:click="deleteActivity({{ $item->id }})" class="p-2 text-gray-400 hover:text-red-500 transition-colors bg-gray-50 hover:bg-red-50 rounded-lg border border-transparent hover:border-red-200" title="Hapus Kegiatan">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-16 text-center">
                                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 text-gray-400 mb-4 border border-gray-200 shadow-inner">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    </div>
                                    <h3 class="text-sm font-bold text-gray-900">Belum Ada Catatan Kinerja</h3>
                                    <p class="text-xs text-gray-500 mt-1">Gunakan tombol "Tambah Kegiatan" atau fitur Tarik Data untuk memulai.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($activities->hasPages())
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $activities->links() }}
                </div>
            @endif
        </div>
    @endif

    <!-- ========================================== -->
    <!-- MODAL GOOGLE SCHOLAR (2 TAHAP) -->
    <!-- ========================================== -->
    @if($isScholarModalOpen)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/40 backdrop-blur-sm p-4 transition-opacity" style="pointer-events: auto;">
            <div class="bg-white rounded-3xl border border-gray-200 shadow-2xl w-full {{ $stepScholar == 1 ? 'max-w-md' : 'max-w-4xl' }} flex flex-col max-h-[90vh] overflow-hidden" onclick="event.stopPropagation()">
                
                <div class="px-6 py-5 border-b border-gray-100 bg-indigo-50/30 flex justify-between items-center shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center shrink-0 border border-indigo-200 shadow-sm">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 leading-tight">
                                {{ $stepScholar == 1 ? 'Tarik Google Scholar' : 'Pilih Artikel (' . $selectedPeriodeName . ')' }}
                            </h3>
                            <p class="text-[10px] text-gray-500 font-medium mt-0.5">Ke Kategori: <span class="font-bold text-indigo-600 uppercase">{{ $activeTab }}</span></p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeScholarModal" class="text-gray-400 hover:text-red-500 transition-colors p-2 bg-gray-50 rounded-lg hover:bg-red-50">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                @if($stepScholar == 1)
                    <form wire:submit.prevent="fetchScholarData" class="flex flex-col">
                        <div class="p-6 space-y-5">
                            <div class="text-xs text-gray-600 bg-yellow-50 p-4 rounded-xl border border-yellow-200 leading-relaxed shadow-sm">
                                Masukkan <strong>URL Profil</strong> atau <strong>ID Google Scholar</strong> Anda untuk mencari publikasi. Sistem akan mencocokkan tahun publikasi dengan tahun periode yang dipilih.
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2">URL Profil / ID Scholar <span class="text-red-500">*</span></label>
                                <input type="text" wire:model="scholar_input" class="block w-full border border-gray-300 rounded-xl py-3 px-4 text-sm focus:ring-indigo-500 focus:border-indigo-500 text-gray-900 font-medium shadow-sm" placeholder="https://scholar.google.com/citations?user=t4nb52cAAAAJ">
                                <p class="text-[10px] mt-2 text-gray-400">Contoh ID: <strong class="text-gray-600">t4nb52cAAAAJ</strong></p>
                                @error('scholar_input') <span class="text-xs text-red-500 mt-1 block font-medium">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        
                        <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/50 flex justify-end gap-3">
                            <button type="button" wire:click="closeScholarModal" class="px-5 py-2.5 text-sm font-bold text-gray-500 hover:text-gray-900 bg-white border border-gray-200 rounded-xl shadow-sm transition-colors">Batal</button>
                            <button type="submit" wire:loading.attr="disabled" class="px-6 py-2.5 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md transition-all flex items-center gap-2 disabled:opacity-70">
                                <span wire:loading.remove wire:target="fetchScholarData">Cari Artikel Sekarang</span>
                                <span wire:loading wire:target="fetchScholarData" class="flex items-center gap-2">
                                    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    Sedang Mencari...
                                </span>
                            </button>
                        </div>
                    </form>
                @else
                    <form wire:submit.prevent="saveSelectedScholar" class="flex flex-col flex-1 min-h-0">
                        <div class="p-6 overflow-y-auto custom-scrollbar flex-1 bg-gray-50/30">
                            <div class="mb-4 flex items-center justify-between">
                                <p class="text-sm font-bold text-gray-700">Ditemukan {{ count($scholar_results) }} Artikel</p>
                                <span class="text-[10px] font-bold text-emerald-600 bg-emerald-100 border border-emerald-200 px-2 py-1 rounded uppercase tracking-wider">
                                    Sistem telah mencentang otomatis artikel yang relevan
                                </span>
                            </div>

                            <div class="space-y-3">
                                @foreach($scholar_results as $res)
                                    <label class="flex items-start gap-4 p-4 border rounded-xl transition-all {{ in_array($res['key'], $selected_scholar_items) ? 'bg-indigo-50/50 border-indigo-300 shadow-sm' : 'bg-white border-gray-200 hover:border-gray-300' }} {{ $res['exists'] ? 'opacity-60 grayscale' : 'cursor-pointer' }}">
                                        
                                        <div class="pt-1">
                                            <input type="checkbox" wire:model="selected_scholar_items" value="{{ $res['key'] }}" class="w-5 h-5 rounded text-indigo-600 focus:ring-indigo-500 border-gray-300 disabled:opacity-50" @if($res['exists']) disabled @endif>
                                        </div>
                                        
                                        <div class="flex-1">
                                            <h4 class="text-sm font-bold text-gray-900 leading-tight mb-1">{{ $res['judul'] }}</h4>
                                            
                                            <div class="flex flex-wrap items-center gap-2 mt-2">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border {{ $res['in_period'] ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : 'bg-gray-100 text-gray-500 border-gray-200' }}">
                                                    Tahun: {{ $res['tahun'] }}
                                                </span>
                                                
                                                @if($res['exists'])
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-700 border-amber-200">
                                                        Sudah Ada di Sistem
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="px-6 py-4 border-t border-gray-100 bg-white flex justify-between items-center shrink-0">
                            <span class="text-xs font-bold text-gray-500">
                                {{ count($selected_scholar_items) }} artikel dipilih
                            </span>
                            <div class="flex gap-3">
                                <button type="button" wire:click="$set('stepScholar', 1)" class="px-4 py-2.5 text-sm font-bold text-gray-500 hover:text-gray-900 bg-gray-100 border border-gray-200 rounded-xl transition-colors">Kembali</button>
                                <button type="submit" wire:loading.attr="disabled" class="px-6 py-2.5 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md transition-all flex items-center gap-2 disabled:opacity-70">
                                    <span wire:loading.remove wire:target="saveSelectedScholar">Simpan Terpilih</span>
                                    <span wire:loading wire:target="saveSelectedScholar" class="flex items-center gap-2">
                                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        Menyimpan...
                                    </span>
                                </button>
                            </div>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @endif

    <!-- ========================================== -->
    <!-- MODAL FORM INPUT UTAMA (KEGIATAN & DOKUMEN) -->
    <!-- ========================================== -->
    @if($isModalOpen)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/40 backdrop-blur-sm p-4 transition-opacity" style="pointer-events: auto;">
            <div class="bg-white rounded-3xl border border-gray-200 shadow-2xl w-full max-w-3xl flex flex-col max-h-[90vh] overflow-hidden" onclick="event.stopPropagation()">
                
                <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center shrink-0">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 leading-tight">
                            {{ $activityId ? 'Ubah Data Tridharma' : 'Catat Data Tridharma' }}
                        </h3>
                        <p class="text-[10px] text-primary font-bold mt-1 uppercase tracking-wider bg-primary/10 px-2 py-0.5 rounded w-max border border-primary/20">
                            Kategori: {{ ucfirst($activeTab) }}
                        </p>
                    </div>
                    <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-red-500 transition-colors p-2 bg-white rounded-lg border border-gray-200 shadow-sm hover:bg-red-50 hover:border-red-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <form wire:submit.prevent="saveActivity" class="flex flex-col overflow-hidden">
                    <div class="p-6 overflow-y-auto custom-scrollbar flex-1">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                            <!-- BAGIAN KIRI: DATA KEGIATAN -->
                            <div class="space-y-5">
                                <h4 class="text-xs font-black text-gray-800 uppercase tracking-widest border-b border-gray-100 pb-2 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    Informasi Dasar
                                </h4>

                                <div>
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Nama / Judul Kegiatan <span class="text-red-500">*</span></label>
                                    <textarea wire:model="judul_kegiatan" rows="2" class="block w-full border border-gray-300 rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-gray-900 font-medium placeholder-gray-400 shadow-sm" placeholder="Misal: Mengajar Algoritma..."></textarea>
                                    @error('judul_kegiatan') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Rubrik SISTER (Opsional)</label>
                                        <input type="text" wire:model="rubrik_kegiatan_id" class="block w-full border border-gray-300 rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-gray-900 shadow-sm" placeholder="Contoh: 110">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Beban SKS <span class="text-red-500">*</span></label>
                                        <input type="number" step="0.1" wire:model="sks_beban" class="block w-full border border-gray-300 rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-gray-900 font-bold shadow-sm text-center" placeholder="0">
                                        @error('sks_beban') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Tanggal Mulai</label>
                                        <input type="date" wire:model="tanggal_mulai" class="block w-full border border-gray-300 rounded-xl py-2 px-3 text-sm focus:ring-primary focus:border-primary text-gray-900 shadow-sm">
                                        @error('tanggal_mulai') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Tanggal Selesai</label>
                                        <input type="date" wire:model="tanggal_selesai" class="block w-full border border-gray-300 rounded-xl py-2 px-3 text-sm focus:ring-primary focus:border-primary text-gray-900 shadow-sm">
                                        @error('tanggal_selesai') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Deskripsi Singkat (Opsional)</label>
                                    <textarea wire:model="deskripsi" rows="2" class="block w-full border border-gray-300 rounded-xl py-2.5 px-3 text-sm focus:ring-primary focus:border-primary text-gray-900 shadow-sm" placeholder="Catatan tambahan..."></textarea>
                                </div>
                            </div>

                            <!-- BAGIAN KANAN: DOKUMEN BUKTI -->
                            <div class="space-y-5">
                                <h4 class="text-xs font-black text-gray-800 uppercase tracking-widest border-b border-gray-100 pb-2 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                                    Dokumen Pendukung
                                </h4>

                                <!-- Area Tambah Dokumen Baru -->
                                <div class="bg-gray-50 p-4 rounded-2xl border border-gray-200 shadow-sm space-y-4">
                                    <p class="text-[10px] text-gray-500 uppercase font-bold tracking-wider mb-2 border-b border-gray-200 pb-1">Tambah Dokumen / Bukti Luaran</p>
                                    
                                    <div>
                                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Nama Dokumen</label>
                                        <input type="text" wire:model="nama_dokumen" class="block w-full border border-gray-300 rounded-lg py-2 px-3 text-xs focus:ring-primary focus:border-primary text-gray-900" placeholder="Misal: Sertifikat, URL Jurnal, dll">
                                        @error('nama_dokumen') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                    
                                    <div class="grid grid-cols-1 gap-3 border-t border-gray-200 pt-3">
                                        <div>
                                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Tautan Luar (URL / Google Drive)</label>
                                            <input type="url" wire:model="tautan_luar" class="block w-full border border-gray-300 rounded-lg py-2 px-3 text-xs focus:ring-primary focus:border-primary text-gray-900" placeholder="https://...">
                                            @error('tautan_luar') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="relative">
                                            <div class="absolute inset-0 flex items-center">
                                                <div class="w-full border-t border-gray-300"></div>
                                            </div>
                                            <div class="relative flex justify-center text-sm">
                                                <span class="px-2 bg-gray-50 text-[9px] text-gray-400 font-bold uppercase tracking-widest">ATAU</span>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Upload File Fisik (Maks 5MB)</label>
                                            <input type="file" wire:model="file_dokumen" class="block w-full border border-gray-300 bg-white rounded-lg py-1.5 px-2 text-xs text-gray-600 file:mr-3 file:py-1 file:px-2.5 file:rounded file:border-0 file:text-[10px] file:font-bold file:uppercase file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 cursor-pointer">
                                            <div wire:loading wire:target="file_dokumen" class="text-[10px] font-bold text-primary mt-1">Mengunggah file...</div>
                                            @error('file_dokumen') <span class="text-[10px] text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                </div>

                                <!-- List Dokumen Yang Sudah Ada (Saat Edit) -->
                                @if(count($existing_documents) > 0)
                                    <div>
                                        <p class="text-[10px] text-gray-500 uppercase font-bold tracking-wider mb-2">Dokumen Tersimpan:</p>
                                        <div class="space-y-2">
                                            @foreach($existing_documents as $doc)
                                                <div class="flex items-center justify-between bg-white border border-gray-200 p-2.5 rounded-xl shadow-sm">
                                                    <div class="flex items-center gap-2 overflow-hidden pr-2">
                                                        @if($doc->file_path)
                                                            <div class="w-8 h-8 rounded bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                                            </div>
                                                        @else
                                                            <div class="w-8 h-8 rounded bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                                                            </div>
                                                        @endif
                                                        <div class="truncate">
                                                            <p class="text-xs font-bold text-gray-900 truncate">{{ $doc->nama_dokumen }}</p>
                                                            <p class="text-[9px] text-gray-400 uppercase tracking-widest">{{ $doc->file_path ? 'File Fisik' : 'Tautan Luar' }}</p>
                                                        </div>
                                                    </div>
                                                    <button type="button" onclick="confirm('Hapus dokumen ini?') || event.stopImmediatePropagation()" wire:click="deleteDocument({{ $doc->id }})" class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors shrink-0">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                    </button>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                    </div>
                    
                    <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex justify-end gap-3 shrink-0">
                        <button type="button" wire:click="closeModal" class="px-5 py-2.5 text-sm font-bold text-gray-500 hover:text-gray-900 bg-white border border-gray-200 rounded-xl shadow-sm transition-colors">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" class="px-6 py-2.5 text-sm font-bold text-white bg-primary hover:bg-primary-hover rounded-xl shadow-md transition-all flex items-center gap-2">
                            <span wire:loading.remove wire:target="saveActivity">Simpan Data Terpadu</span>
                            <span wire:loading wire:target="saveActivity" class="flex items-center gap-2">
                                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Menyimpan...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>