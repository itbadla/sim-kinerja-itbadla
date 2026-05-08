<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Unit;
use App\Models\Position;
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

        // 3. BUAT USER PIMPINAN UTAMA
        $pimpinanData = [
            ['name' => 'Prof. Dr. Rektor Utama', 'email' => 'rektor@ahmaddahlan.ac.id', 'role' => 'Rektor', 'pos' => 'Rektor'],
            ['name' => 'Wakil Rektor I (Akademik)', 'email' => 'wr1@ahmaddahlan.ac.id', 'role' => 'Wakil Rektor', 'pos' => 'Wakil Rektor'],
            ['name' => 'Wakil Rektor II (Keuangan)', 'email' => 'wr2@ahmaddahlan.ac.id', 'role' => 'Wakil Rektor', 'pos' => 'Wakil Rektor'],
            ['name' => 'Wakil Rektor III (Kemahasiswaan)', 'email' => 'wr3@ahmaddahlan.ac.id', 'role' => 'Wakil Rektor', 'pos' => 'Wakil Rektor'],
            ['name' => 'Ketua LPM', 'email' => 'lpm@ahmaddahlan.ac.id', 'role' => 'Ketua LPM', 'pos' => 'Kepala Lembaga'],
            ['name' => 'Ketua LPPM', 'email' => 'lppm@ahmaddahlan.ac.id', 'role' => 'Ketua LPPM', 'pos' => 'Kepala Lembaga'],
            ['name' => 'Ibu Sekretaris Rektorat', 'email' => 'sekretaris@ahmaddahlan.ac.id', 'role' => 'Sekretaris Rektorat', 'pos' => 'Staff'],
            ['name' => 'Admin Jarkomit', 'email' => 'jarkomit@ahmaddahlan.ac.id', 'role' => 'Super Admin', 'pos' => 'Kepala Unit'],
            ['name' => 'Dekan Fakultas Teknik', 'email' => 'dekan.ft@ahmaddahlan.ac.id', 'role' => 'Dekan', 'pos' => 'Dekan'],
            ['name' => 'Dekan FEB', 'email' => 'dekan.feb@ahmaddahlan.ac.id', 'role' => 'Dekan', 'pos' => 'Dekan'],
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

        // 6. PRODI & KAPRODI (LEVEL 4)
        $prodis = [
            ['kode' => 'TI', 'nama' => 'Teknologi Informasi', 'parent' => $ft->id, 'email' => 'kaprodi.ti@ahmaddahlan.ac.id'],
            ['kode' => 'SIPIL', 'nama' => 'Teknik Sipil', 'parent' => $ft->id, 'email' => 'kaprodi.sipil@ahmaddahlan.ac.id'],
            ['kode' => 'ARSI', 'nama' => 'Arsitektur', 'parent' => $ft->id, 'email' => 'kaprodi.arsi@ahmaddahlan.ac.id'],
            ['kode' => 'AKUN', 'nama' => 'Akuntansi', 'parent' => $feb->id, 'email' => 'kaprodi.akun@ahmaddahlan.ac.id'],
            ['kode' => 'MANAJ', 'nama' => 'Manajemen', 'parent' => $feb->id, 'email' => 'kaprodi.manaj@ahmaddahlan.ac.id'],
            ['kode' => 'BDIG', 'nama' => 'Bisnis Digital', 'parent' => $feb->id, 'email' => 'kaprodi.bdig@ahmaddahlan.ac.id'],
        ];

        foreach ($prodis as $p) {
            $kpUser = User::create([
                'name' => 'Kaprodi ' . $p['nama'],
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

            // Gunakan Position ID (Kaprodi = Level 3)
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

        // 9. MASTER DATA INDIKATOR
        PerformanceIndicator::firstOrCreate(['kode_indikator' => 'IKU-1'], ['nama_indikator' => 'Lulusan Mendapat Pekerjaan Layak', 'kategori' => 'IKU']);
        PerformanceIndicator::firstOrCreate(['kode_indikator' => 'IKT-01'], ['nama_indikator' => 'Publikasi Jurnal Internasional', 'kategori' => 'IKT']);
    }
}