<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cache Spatie
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // --- STEP 1: PERMISSIONS ---
        $permissions = [
            'akses-dashboard', 'akses-profil',
            'isi-logbook', 'verifikasi-logbook', 'akses-tridharma',
            'ajukan-dana', 'verifikasi-dana',
            'monitoring-unit', 'akses-dokumen',
            'kelola-user', 'kelola-unit', 'kelola-role', 'kelola-master'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // --- STEP 2: ROLES ---
        
        // 1. Admin: Memegang semua akses
        $roleAdmin = Role::firstOrCreate(['name' => 'admin']);
        $roleAdmin->syncPermissions(Permission::all());

        // 2. Pimpinan (Kepala Unit/Lembaga): Verifikasi & Monitoring
        $rolePimpinan = Role::firstOrCreate(['name' => 'pimpinan'])->syncPermissions([
            'akses-dashboard', 'akses-profil', 'isi-logbook', 'verifikasi-logbook', 
            'monitoring-unit', 'ajukan-dana'
        ]);

        // 3. Dosen: Fokus pada Logbook & Tridharma
        $roleDosen = Role::firstOrCreate(['name' => 'dosen'])->syncPermissions([
            'akses-dashboard', 'akses-profil', 'isi-logbook', 'akses-tridharma', 
            'ajukan-dana', 'akses-dokumen'
        ]);

        // 4. Staff (Administrasi/Teknis): Anggota Unit Biasa
        $roleStaff = Role::firstOrCreate(['name' => 'staff'])->syncPermissions([
            'akses-dashboard', 'akses-profil', 'isi-logbook', 'akses-dokumen'
        ]);

        // 5. Keuangan: Verifikasi Anggaran
        $roleKeuangan = Role::firstOrCreate(['name' => 'keuangan'])->syncPermissions([
            'akses-dashboard', 'verifikasi-dana', 'akses-dokumen'
        ]);


        // --- STEP 3: SEEDING UNITS ---
        $units = [
            ['kode' => 'JARKOMIT', 'nama' => 'Jaringan Komunikasi & IT'],
            ['kode' => 'BAUK', 'nama' => 'Biro Administrasi Umum & Keuangan'],
            ['kode' => 'LPPM', 'nama' => 'Lembaga Penelitian & Pengabdian Masyarakat'],
            ['kode' => 'LPM', 'nama' => 'Lembaga Penjaminan Mutu'],
        ];

        foreach ($units as $u) {
            Unit::updateOrCreate(['kode_unit' => $u['kode']], ['nama_unit' => $u['nama']]);
        }

        // Fakultas & Prodi
        $ft = Unit::updateOrCreate(['kode_unit' => 'FT'], ['nama_unit' => 'Fakultas Teknik']);
        $uTI = Unit::updateOrCreate(['kode_unit' => 'TI'], ['nama_unit' => 'Prodi Teknik Informatika', 'parent_id' => $ft->id]);


        // --- STEP 4: SEEDING USERS & PLOTTING ---

        // 1. SUPER ADMIN
        $uJarkomit = Unit::where('kode_unit', 'JARKOMIT')->first();
        $admin = User::updateOrCreate(
            ['email' => 'jarkomit@ahmaddahlan.ac.id'],
            [
                'name' => 'Administrator Jarkomit', 
                'password' => bcrypt('password'), 
                'email_verified_at' => now(),
                'unit_id' => $uJarkomit->id, 
                'jabatan' => 'Kepala Jarkomit'
            ]
        );
        $admin->assignRole($roleAdmin);
        $uJarkomit->update(['kepala_unit_id' => $admin->id]);
        // TAMBAHKAN BARIS INI: Masukkan ke daftar members
        $uJarkomit->members()->syncWithoutDetaching([$admin->id]);


        // 2. DOSEN + PIMPINAN
        $kaprodiTI = User::updateOrCreate(
            ['email' => 'ahmad@ahmaddahlan.ac.id'],
            [
                'name' => 'Ahmad, M.Kom', 
                'password' => bcrypt('password'), 
                'unit_id' => $uTI->id, 
                'jabatan' => 'Kaprodi TI'
            ]
        );
        $kaprodiTI->assignRole([$roleDosen, $rolePimpinan]);
        $uTI->update(['kepala_unit_id' => $kaprodiTI->id]);
        // TAMBAHKAN BARIS INI
        $uTI->members()->syncWithoutDetaching([$kaprodiTI->id]);


        // 3. STAFF UNIT
        $staffIT = User::updateOrCreate(
            ['email' => 'staff.it@ahmaddahlan.ac.id'],
            [
                'name' => 'Budi IT Support', 
                'password' => bcrypt('password'), 
                'unit_id' => $uJarkomit->id, 
                'jabatan' => 'Staff Jarkomit'
            ]
        );
        $staffIT->assignRole($roleStaff);
        // TAMBAHKAN BARIS INI
        $uJarkomit->members()->syncWithoutDetaching([$staffIT->id]);


        // 4. KEPALA BIRO KEUANGAN
        $uBAUK = Unit::where('kode_unit', 'BAUK')->first();
        $kaBAUK = User::updateOrCreate(
            ['email' => 'keuangan@ahmaddahlan.ac.id'],
            [
                'name' => 'Siti Bendahara, S.E.', 
                'password' => bcrypt('password'), 
                'unit_id' => $uBAUK->id, 
                'jabatan' => 'Kepala BAUK'
            ]
        );
        $kaBAUK->assignRole([$roleStaff, $rolePimpinan, $roleKeuangan]);
        $uBAUK->update(['kepala_unit_id' => $kaBAUK->id]);
        // TAMBAHKAN BARIS INI
        $uBAUK->members()->syncWithoutDetaching([$kaBAUK->id]);


        // 5. DOSEN BIASA
        $dosenBiasa = User::updateOrCreate(
            ['email' => 'dosen.ti@ahmaddahlan.ac.id'],
            [
                'name' => 'Dosen TI 1, M.T.', 
                'password' => bcrypt('password'), 
                'unit_id' => $uTI->id, 
                'jabatan' => 'Dosen Tetap'
            ]
        );
        $dosenBiasa->assignRole($roleDosen);
        // TAMBAHKAN BARIS INI
        $uTI->members()->syncWithoutDetaching([$dosenBiasa->id]);


        // 5. DOSEN BIASA (Anggota Unit TI)
        $dosenBiasa = User::updateOrCreate(
            ['email' => 'dosen.ti@ahmaddahlan.ac.id'],
            [
                'name' => 'Dosen TI 1, M.T.', 
                'password' => bcrypt('password'), 
                'unit_id' => $uTI->id, 
                'jabatan' => 'Dosen Tetap'
            ]
        );
        $dosenBiasa->assignRole($roleDosen);
    }
}