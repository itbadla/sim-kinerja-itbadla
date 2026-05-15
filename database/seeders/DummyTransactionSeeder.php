<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Unit;
use App\Models\Periode;
use App\Models\WorkProgram;
use App\Models\FundSubmission;
use App\Models\FundDisbursement;
use App\Models\Logbook;
use App\Models\PerformanceIndicator; // Tambahkan ini
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Faker\Factory as Faker;

class DummyTransactionSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');

        // 1. Ambil Periode Aktif
        $periodeAktif = Periode::where('is_current', true)->first();
        if (!$periodeAktif) {
            $this->command->error('Tidak ada periode aktif. Jalankan DatabaseSeeder terlebih dahulu.');
            return;
        }

        // 2. Ambil semua Unit yang punya Kepala Unit
        $units = Unit::whereNotNull('kepala_unit_id')->get();
        if ($units->isEmpty()) {
            $this->command->error('Tidak ada unit yang ditemukan. Jalankan DatabaseSeeder terlebih dahulu.');
            return;
        }

        // 3. Ambil WR2 untuk verifikator keuangan
        $wr2 = User::where('email', 'wr2@ahmaddahlan.ac.id')->first();

        // =========================================================
        // TAMBAHAN: CEK ATAU BUAT INDIKATOR KINERJA (IKU/IKT)
        // =========================================================
        $this->command->info('Memastikan data 8 IKU dan IKT lengkap...');

        $indikatorData = [
            // 8 IKU KEMENTERIAN (KEPMENDIKBUDRISTEK)
            ['kode_indikator' => 'IKU-1', 'nama_indikator' => 'Lulusan Mendapat Pekerjaan yang Layak', 'kategori' => 'IKU'],
            ['kode_indikator' => 'IKU-2', 'nama_indikator' => 'Mahasiswa Mendapat Pengalaman di Luar Kampus', 'kategori' => 'IKU'],
            ['kode_indikator' => 'IKU-3', 'nama_indikator' => 'Dosen Berkegiatan di Luar Kampus', 'kategori' => 'IKU'],
            ['kode_indikator' => 'IKU-4', 'nama_indikator' => 'Praktisi Mengajar di Dalam Kampus', 'kategori' => 'IKU'],
            ['kode_indikator' => 'IKU-5', 'nama_indikator' => 'Hasil Kerja Dosen Digunakan oleh Masyarakat', 'kategori' => 'IKU'],
            ['kode_indikator' => 'IKU-6', 'nama_indikator' => 'Program Studi Bekerjasama dengan Mitra Kelas Dunia', 'kategori' => 'IKU'],
            ['kode_indikator' => 'IKU-7', 'nama_indikator' => 'Kelas yang Kolaboratif dan Partisipatif', 'kategori' => 'IKU'],
            ['kode_indikator' => 'IKU-8', 'nama_indikator' => 'Program Studi Berstandar Internasional', 'kategori' => 'IKU'],

            // IKT (INDIKATOR KINERJA TAMBAHAN - KHUSUS KAMPUS)
            ['kode_indikator' => 'IKT-1', 'nama_indikator' => 'Persentase Lulusan Tepat Waktu (<= 4 Tahun)', 'kategori' => 'IKT'],
            ['kode_indikator' => 'IKT-2', 'nama_indikator' => 'Publikasi Jurnal Internasional Terindeks Scopus (Non-IKU)', 'kategori' => 'IKT'],
            ['kode_indikator' => 'IKT-3', 'nama_indikator' => 'Implementasi Nilai Al-Islam dan Kemuhammadiyahan (AIK)', 'kategori' => 'IKT'],
            ['kode_indikator' => 'IKT-4', 'nama_indikator' => 'Pencapaian Akreditasi Unggul/A untuk Program Studi', 'kategori' => 'IKT'],
        ];

        foreach ($indikatorData as $ind) {
            PerformanceIndicator::firstOrCreate(
                ['kode_indikator' => $ind['kode_indikator'], 'periode_id' => $periodeAktif->id],
                ['nama_indikator' => $ind['nama_indikator'], 'kategori' => $ind['kategori']]
            );
        }

        // Ambil ulang semua indikator yang sudah dipastikan lengkap
        $indicators = PerformanceIndicator::where('periode_id', $periodeAktif->id)->get();

        $satuanTarget = ['%', 'Orang', 'Kegiatan', 'Dokumen', 'Mitra', 'SKS'];

        $this->command->info('Memulai seeding data transaksi dummy...');

        // =========================================================
        // A. PROGRAM KERJA & KEUANGAN (Per Unit)
        // =========================================================
        foreach ($units as $unit) {
            $this->command->info("Membuat Proker untuk Unit: {$unit->nama_unit}");
            
            // Buat 3-5 Program Kerja per Unit
            $jumlahProker = rand(3, 5);
            for ($i = 0; $i < $jumlahProker; $i++) {
                
                // Variasi Status Proker
                $statusRand = rand(1, 10);
                $statusProker = 'disetujui';
                if ($statusRand > 8) $statusProker = 'draft';
                elseif ($statusRand > 7) $statusProker = 'review_lpm';

                $anggaranRencana = rand(5, 50) * 1000000; // Rp 5jt - Rp 50jt

                $proker = WorkProgram::create([
                    'unit_id' => $unit->id,
                    'periode_id' => $periodeAktif->id,
                    'nama_proker' => "Kegiatan " . $faker->words(3, true) . " " . $unit->kode_unit,
                    'deskripsi' => $faker->paragraph(),
                    'anggaran_rencana' => $anggaranRencana,
                    'status' => $statusProker,
                ]);

                // =========================================================
                // TAMBAHAN: HUBUNGKAN PROKER DENGAN 1-3 IKU/IKT SECARA ACAK
                // =========================================================
                $jumlahIndikator = rand(1, 3);
                $randomIndicators = $indicators->random(min($jumlahIndikator, $indicators->count()));
                
                foreach ($randomIndicators as $indicator) {
                    DB::table('work_program_indicators')->insert([
                        'work_program_id' => $proker->id,
                        'indicator_id' => $indicator->id,
                        'target_angka' => rand(5, 100), // Target angka dummy
                        'satuan_target' => $satuanTarget[array_rand($satuanTarget)] // Satuan acak
                    ]);
                }

                // B. JIKA PROKER DISETUJUI, BUAT PENGAJUAN DANA
                if ($statusProker === 'disetujui' && rand(1, 10) > 2) {
                    
                    // Nominal disetujui biasanya lebih kecil/sama dengan rencana
                    $nominalDisetujui = $anggaranRencana - (rand(0, 5) * 1000000); 
                    
                    $statusPengajuan = rand(1, 10) > 2 ? 'approved' : 'pending';

                    $submission = FundSubmission::create([
                        'user_id' => $unit->kepala_unit_id,
                        'unit_id' => $unit->id,
                        'work_program_id' => $proker->id,
                        'periode_id' => $periodeAktif->id,
                        'tipe_pengajuan' => 'lembaga',
                        'nominal_total' => $anggaranRencana,
                        'nominal_disetujui' => $statusPengajuan === 'approved' ? $nominalDisetujui : null,
                        'keperluan' => "Pencairan dana kegiatan " . $proker->nama_proker,
                        'status_pengajuan' => $statusPengajuan,
                        'verified_by' => $statusPengajuan === 'approved' ? ($wr2 ? $wr2->id : null) : null,
                        'verified_at' => $statusPengajuan === 'approved' ? now()->subDays(rand(10, 30)) : null,
                        'skema_pencairan' => 'lumpsum'
                    ]);

                    // C. JIKA PENGAJUAN APPROVED, BUAT PENCAIRAN & LPJ
                    if ($statusPengajuan === 'approved') {
                        $statusCair = rand(1, 10) > 1 ? 'cair' : 'diproses';
                        
                        $statusLpj = 'belum';
                        if ($statusCair === 'cair') {
                            $lpjRand = rand(1, 10);
                            if ($lpjRand > 5) $statusLpj = 'selesai';
                            elseif ($lpjRand > 3) $statusLpj = 'menunggu_verifikasi';
                        }

                        $nominalRealisasi = $statusLpj === 'selesai' ? $nominalDisetujui - (rand(0, 10) * 100000) : null;
                        $nominalKembali = ($statusLpj === 'selesai' && $nominalRealisasi < $nominalDisetujui) ? ($nominalDisetujui - $nominalRealisasi) : null;

                        FundDisbursement::create([
                            'fund_submission_id' => $submission->id,
                            'termin_ke' => 1,
                            'nominal_cair' => $nominalDisetujui,
                            'status_cair' => $statusCair,
                            'tanggal_cair' => $statusCair === 'cair' ? now()->subDays(rand(5, 20)) : null,
                            'status_lpj' => $statusLpj,
                            'nominal_realisasi' => $nominalRealisasi,
                            'nominal_kembali' => $nominalKembali,
                            'status_pengembalian' => $nominalKembali > 0 ? (rand(0,1) ? 'lunas' : 'menunggu_verifikasi') : 'tidak_ada'
                        ]);
                    }
                }
            }
        }

        // =========================================================
        // D. LOGBOOK HARIAN & BKD (Untuk seluruh User)
        // =========================================================
        $users = User::all();
        $bulanIni = Carbon::now()->month;
        $tahunIni = Carbon::now()->year;

        $this->command->info('Membuat Data Logbook dan BKD untuk Dosen/Staff...');

        foreach ($users as $user) {
            
            // D1. LOGBOOK (Buat 10-20 logbook bulan ini untuk setiap user)
            $jumlahLogbook = rand(10, 20);
            for ($i = 0; $i < $jumlahLogbook; $i++) {
                $tanggal = Carbon::createFromDate($tahunIni, $bulanIni, rand(1, 28));
                
                $jamMulai = rand(8, 10);
                $jamSelesai = $jamMulai + rand(2, 6);

                $statusLog = rand(1, 10) > 2 ? 'approved' : 'pending';

                Logbook::create([
                    'user_id' => $user->id,
                    'periode_id' => $periodeAktif->id,
                    'tanggal' => $tanggal->format('Y-m-d'),
                    'jam_mulai' => sprintf('%02d:00:00', $jamMulai),
                    'jam_selesai' => sprintf('%02d:00:00', $jamSelesai),
                    'kategori' => 'tugas_utama',
                    'deskripsi_aktivitas' => $faker->sentence(6),
                    'output' => 'Dokumen / Laporan',
                    'status' => $statusLog,
                    'verified_at' => $statusLog === 'approved' ? $tanggal->addDay() : null,
                ]);
            }

            // D2. BKD (Hanya untuk Dosen/Kaprodi/Dekan)
            // Deteksi apakah user punya peran akademik
            $isAkademisi = str_contains($user->email, 'kaprodi') || str_contains($user->email, 'dekan') || $user->hasRole('Dosen');
            
            if ($isAkademisi) {
                // Buat 2-4 Kegiatan Tridharma per Dosen
                $jumlahBkd = rand(2, 4);
                $kategoriEnum = ['pendidikan', 'penelitian', 'pengabdian', 'penunjang'];

                for ($j = 0; $j < $jumlahBkd; $j++) {
                    
                    $kategori = $kategoriEnum[array_rand($kategoriEnum)];
                    $statusInternal = rand(1, 10) > 3 ? 'approved' : 'draft';
                    $syncStatus = $statusInternal === 'approved' && rand(1, 10) > 4 ? 'synced' : 'un-synced';

                    $bkdActivity = DB::table('bkd_activities')->insertGetId([
                        'user_id' => $user->id,
                        'periode_id' => $periodeAktif->id,
                        'sister_id' => $syncStatus === 'synced' ? (string) \Illuminate\Support\Str::uuid() : null,
                        'kategori_tridharma' => $kategori,
                        'judul_kegiatan' => "Kegiatan " . ucfirst($kategori) . " " . $faker->words(3, true),
                        'tanggal_mulai' => now()->subMonths(rand(1, 3)),
                        'tanggal_selesai' => now()->subDays(rand(1, 30)),
                        'deskripsi' => $faker->paragraph(),
                        'sks_beban' => rand(1, 4) + (rand(0, 1) * 0.5),
                        'sync_status' => $syncStatus,
                        'last_synced_at' => $syncStatus === 'synced' ? now() : null,
                        'status_internal' => $statusInternal,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Insert ke bkd_members (Diri Sendiri sebagai Ketua)
                    DB::table('bkd_members')->insert([
                        'bkd_activity_id' => $bkdActivity,
                        'user_id' => $user->id,
                        'peran' => 'ketua',
                        'is_aktif' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $this->command->info('Seeding transaksi dummy selesai! Dashboard Anda sekarang sudah penuh dengan data.');
    }
}