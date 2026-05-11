<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Unit;
use App\Models\Position;
use App\Models\Periode;
use App\Models\PerformanceIndicator;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. INISIALISASI ROLE (Spatie Permission)
        $roles = [
            'Super Admin', 'Rektor', 'Wakil Rektor', 'Ketua LPM', 
            'Ketua LPPM', 'Sekretaris Rektorat', 'Kepala Biro', 
            'Dekan', 'Kaprodi', 'Dosen', 'Staff'
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // 2. MASTER JABATAN (Positions)
        // Level Otoritas: 1 (Tertinggi) s/d 5 (Staff)
        $positionsData = [
            ['nama_jabatan' => 'Rektor', 'level_otoritas' => 1, 'kategori' => 'Pimpinan'],
            ['nama_jabatan' => 'Wakil Rektor', 'level_otoritas' => 2, 'kategori' => 'Pimpinan'],
            ['nama_jabatan' => 'Dekan', 'level_otoritas' => 2, 'kategori' => 'Struktural'],
            ['nama_jabatan' => 'Kepala Lembaga', 'level_otoritas' => 3, 'kategori' => 'Struktural'],
            ['nama_jabatan' => 'Kepala Biro', 'level_otoritas' => 3, 'kategori' => 'Struktural'],
            ['nama_jabatan' => 'Kepala Unit', 'level_otoritas' => 3, 'kategori' => 'Struktural'],
            ['nama_jabatan' => 'Kaprodi', 'level_otoritas' => 3, 'kategori' => 'Struktural'],
            ['nama_jabatan' => 'Dosen', 'level_otoritas' => 4, 'kategori' => 'Akademik'],
            ['nama_jabatan' => 'Staff', 'level_otoritas' => 5, 'kategori' => 'Administratif'],
        ];

        $positions = [];
        foreach ($positionsData as $pos) {
            $positions[$pos['nama_jabatan']] = Position::firstOrCreate(
                ['nama_jabatan' => $pos['nama_jabatan']], 
                $pos
            );
        }

        $password = Hash::make('password');

        // 3. BUAT USER PIMPINAN UTAMA (Dengan Nama Realistis)
        $pimpinanData = [
            ['name' => 'Prof. Dr. H. Budi Santoso, S.E., M.M.', 'email' => 'rektor@ahmaddahlan.ac.id', 'role' => 'Rektor', 'pos' => 'Rektor'],
            ['name' => 'Dr. Hj. Siti Aminah, M.Pd.', 'email' => 'wr1@ahmaddahlan.ac.id', 'role' => 'Wakil Rektor', 'pos' => 'Wakil Rektor'],
            ['name' => 'Dr. Andi Wijaya, S.T., M.T.', 'email' => 'wr2@ahmaddahlan.ac.id', 'role' => 'Wakil Rektor', 'pos' => 'Wakil Rektor'],
            ['name' => 'Dr. Rina Puspita, S.H., M.H.', 'email' => 'wr3@ahmaddahlan.ac.id', 'role' => 'Wakil Rektor', 'pos' => 'Wakil Rektor'],
            ['name' => 'Dr. Hendra Gunawan, S.Si., M.Sc.', 'email' => 'lpm@ahmaddahlan.ac.id', 'role' => 'Ketua LPM', 'pos' => 'Kepala Lembaga'],
            ['name' => 'Prof. Dr. Wahyu Hidayat, M.Si.', 'email' => 'lppm@ahmaddahlan.ac.id', 'role' => 'Ketua LPPM', 'pos' => 'Kepala Lembaga'],
            ['name' => 'Nita Marlina, S.I.Kom.', 'email' => 'sekretaris@ahmaddahlan.ac.id', 'role' => 'Sekretaris Rektorat', 'pos' => 'Staff'],
            ['name' => 'Ahmad Zafa, S.Kom.', 'email' => 'jarkomit@ahmaddahlan.ac.id', 'role' => 'Super Admin', 'pos' => 'Kepala Unit'],
            ['name' => 'Dr. Bambang Riyanto, S.T., M.Kom.', 'email' => 'dekan.ft@ahmaddahlan.ac.id', 'role' => 'Dekan', 'pos' => 'Dekan'],
            ['name' => 'Dr. Maya Fitriani, S.E., M.Ak.', 'email' => 'dekan.feb@ahmaddahlan.ac.id', 'role' => 'Dekan', 'pos' => 'Dekan'],
        ];

        $users = [];
        foreach ($pimpinanData as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                ['name' => $data['name'], 'password' => $password, 'email_verified_at' => now()]
            );
            $user->syncRoles([$data['role']]);
            $users[$data['email']] = [
                'id' => $user->id,
                'pos_id' => $positions[$data['pos']]->id
            ];
        }

        // 4. STRUKTUR UNIT (LEVEL 1 & 2)
        $rektorat = Unit::create(['kode_unit' => 'REK', 'nama_unit' => 'Rektorat', 'kepala_unit_id' => $users['rektor@ahmaddahlan.ac.id']['id']]);
        
        $wr1 = Unit::create(['kode_unit' => 'WR1', 'nama_unit' => 'Wakil Rektor I (Akademik)', 'parent_id' => $rektorat->id, 'kepala_unit_id' => $users['wr1@ahmaddahlan.ac.id']['id']]);
        $wr2 = Unit::create(['kode_unit' => 'WR2', 'nama_unit' => 'Wakil Rektor II (Keuangan & SDM)', 'parent_id' => $rektorat->id, 'kepala_unit_id' => $users['wr2@ahmaddahlan.ac.id']['id']]);
        $wr3 = Unit::create(['kode_unit' => 'WR3', 'nama_unit' => 'Wakil Rektor III (Kemahasiswaan & AIK)', 'parent_id' => $rektorat->id, 'kepala_unit_id' => $users['wr3@ahmaddahlan.ac.id']['id']]);

        // 5. UNIT LEVEL 3 (DI BAWAH WR)
        $lpm = Unit::create(['kode_unit' => 'LPM', 'nama_unit' => 'Lembaga Penjaminan Mutu', 'parent_id' => $wr1->id, 'kepala_unit_id' => $users['lpm@ahmaddahlan.ac.id']['id']]);
        $lppm = Unit::create(['kode_unit' => 'LPPM', 'nama_unit' => 'Lembaga Penelitian & Pengabdian', 'parent_id' => $wr1->id, 'kepala_unit_id' => $users['lppm@ahmaddahlan.ac.id']['id']]);
        $ft = Unit::create(['kode_unit' => 'FT', 'nama_unit' => 'Fakultas Teknik', 'parent_id' => $wr1->id, 'kepala_unit_id' => $users['dekan.ft@ahmaddahlan.ac.id']['id']]);
        $feb = Unit::create(['kode_unit' => 'FEB', 'nama_unit' => 'Fakultas Ekonomi & Bisnis', 'parent_id' => $wr1->id, 'kepala_unit_id' => $users['dekan.feb@ahmaddahlan.ac.id']['id']]);

        Unit::create(['kode_unit' => 'JARKOM', 'nama_unit' => 'Pusat Jarkomit', 'parent_id' => $wr2->id, 'kepala_unit_id' => $users['jarkomit@ahmaddahlan.ac.id']['id']]);
        Unit::create(['kode_unit' => 'SDI', 'nama_unit' => 'Biro Sumber Daya Insani', 'parent_id' => $wr2->id]);
        Unit::create(['kode_unit' => 'LAIK', 'nama_unit' => 'Lembaga AIK', 'parent_id' => $wr3->id]);

        // 6. PRODI & KAPRODI (LEVEL 4) - Ditambah nama asli
        $prodis = [
            ['kode' => 'TI', 'nama' => 'Teknologi Informasi', 'parent' => $ft->id, 'email' => 'kaprodi.ti@ahmaddahlan.ac.id', 'nama_kaprodi' => 'Rizal Efendi, M.Kom.'],
            ['kode' => 'SIPIL', 'nama' => 'Teknik Sipil', 'parent' => $ft->id, 'email' => 'kaprodi.sipil@ahmaddahlan.ac.id', 'nama_kaprodi' => 'Eko Prasetyo, M.T.'],
            ['kode' => 'ARSI', 'nama' => 'Arsitektur', 'parent' => $ft->id, 'email' => 'kaprodi.arsi@ahmaddahlan.ac.id', 'nama_kaprodi' => 'Dian Novita, M.Ars.'],
            ['kode' => 'AKUN', 'nama' => 'Akuntansi', 'parent' => $feb->id, 'email' => 'kaprodi.akun@ahmaddahlan.ac.id', 'nama_kaprodi' => 'Reni Anggraeni, M.Ak.'],
            ['kode' => 'MANAJ', 'nama' => 'Manajemen', 'parent' => $feb->id, 'email' => 'kaprodi.manaj@ahmaddahlan.ac.id', 'nama_kaprodi' => 'Dwi Cahyono, M.M.'],
            ['kode' => 'BDIG', 'nama' => 'Bisnis Digital', 'parent' => $feb->id, 'email' => 'kaprodi.bdig@ahmaddahlan.ac.id', 'nama_kaprodi' => 'Fitri Handayani, M.B.A.'],
        ];

        foreach ($prodis as $p) {
            $kpUser = User::create([
                'name' => $p['nama_kaprodi'],
                'email' => $p['email'],
                'password' => $password,
                'email_verified_at' => now(),
            ]);
            $kpUser->assignRole(['Kaprodi', 'Dosen']);

            $uProdi = Unit::create([
                'kode_unit' => $p['kode'],
                'nama_unit' => $p['nama'],
                'parent_id' => $p['parent'],
                'kepala_unit_id' => $kpUser->id
            ]);

            // Gunakan Position ID (Kaprodi = Level 3/4)
            $kpUser->units()->attach($uProdi->id, [
                'position_id' => $positions['Kaprodi']->id,
                'is_active' => true
            ]);
        }

        // 7. PLOTTING PIMPINAN UTAMA KE UNIT_USER PIVOT
        // Hal ini agar pimpinan terdeteksi sebagai anggota di unitnya sendiri
        foreach ($pimpinanData as $pData) {
            $uId = $users[$pData['email']]['id'];
            $pId = $users[$pData['email']]['pos_id'];
            
            // Cari unit mana yang dipimpin oleh user ini
            $unitLed = Unit::where('kepala_unit_id', $uId)->get();
            foreach ($unitLed as $ul) {
                User::find($uId)->units()->syncWithoutDetaching([
                    $ul->id => ['position_id' => $pId, 'is_active' => true]
                ]);
            }
        }

        // 8. JALANKAN PERMISSION SEEDER
        $this->call([PermissionSeeder::class]);

        // 9. MASTER DATA PERIODE & INDIKATOR KINERJA
        
        // Buat Data Periode (Renstra/Tahun Akademik)
        $periodeData = [
            ['nama_periode' => 'TA 2024/2025', 'tanggal_mulai' => '2024-09-01', 'tanggal_selesai' => '2025-08-31', 'status' => 'closed', 'is_current' => false],
            ['nama_periode' => 'TA 2025/2026', 'tanggal_mulai' => '2025-09-01', 'tanggal_selesai' => '2026-08-31', 'status' => 'active', 'is_current' => true], // Kita set 25/26 sebagai aktif saat ini
            ['nama_periode' => 'TA 2026/2027', 'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2027-08-31', 'status' => 'planning', 'is_current' => false],
        ];

        $activePeriodeId = null;
        foreach ($periodeData as $p) {
            $periode = Periode::firstOrCreate(
                ['nama_periode' => $p['nama_periode']],
                $p
            );
            if ($p['is_current']) {
                $activePeriodeId = $periode->id;
            }
        }

        // Buat Master Data IKU/IKT untuk Periode yang Aktif
        if ($activePeriodeId) {
            PerformanceIndicator::firstOrCreate(
                ['kode_indikator' => 'IKU-1', 'periode_id' => $activePeriodeId], 
                ['nama_indikator' => 'Lulusan Mendapat Pekerjaan Layak', 'kategori' => 'IKU']
            );
            
            PerformanceIndicator::firstOrCreate(
                ['kode_indikator' => 'IKU-2', 'periode_id' => $activePeriodeId], 
                ['nama_indikator' => 'Mahasiswa Mendapat Pengalaman di Luar Kampus', 'kategori' => 'IKU']
            );

            PerformanceIndicator::firstOrCreate(
                ['kode_indikator' => 'IKT-01', 'periode_id' => $activePeriodeId], 
                ['nama_indikator' => 'Publikasi Jurnal Internasional', 'kategori' => 'IKT']
            );
        }
    }
}