<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cache role dan permission (Sangat penting agar perubahan langsung terbaca)
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. DAFTAR PERMISSION
        $permissions = [
            // khusus pimpinan
            'monitoring-universitas',
            
            // Utama
            'dasbor', 'profil-saya',
            
            // Perencanaan
            'program-kerja', 'verifikasi-raker',
            
            // Kinerja
            'logbook-harian', 'verifikasi-logbook', 'team-saya',
            
            // Keuangan
            'pengajuan-dana', 'verifikasi-keuangan', 'laporan-lpj',
            
            // Administrator
            'kelola-user', 'kelola-unit', 'peran-dan-izin', 'master-jabatan', 'indikator-kinerja'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // 2. DISTRIBUSI KE ROLE
        
        // Super Admin: Mendapatkan akses penuh ke seluruh fitur sistem
        $superAdmin = Role::where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo(Permission::all());
        }

        // PASTIKAN REKTOR & WR PUNYA PERMISSION INI
        $pimpinanTertinggi = ['Rektor', 'Wakil Rektor'];
        foreach ($pimpinanTertinggi as $rName) {
            $role = Role::where('name', $rName)->first();
            if ($role) {
                $role->givePermissionTo([
                    'dasbor', 
                    'profil-saya', 
                    'verifikasi-raker', 
                    'verifikasi-logbook', // <--- PENTING
                    'team-saya',          // <--- PENTING
                    'monitoring-universitas'
                ]);
            }
        }

        // Manajerial Unit: Dekan, Kaprodi, dan Kepala Lembaga/Biro
        $manajerialRoles = ['Kepala Lembaga', 'Kepala Biro', 'Dekan', 'Kaprodi'];
        foreach ($manajerialRoles as $rName) {
            $role = Role::where('name', $rName)->first();
            if ($role) {
                $role->givePermissionTo(['dasbor', 'profil-saya', 'program-kerja', 'logbook-harian', 'verifikasi-logbook', 'pengajuan-dana', 'team-saya']);
            }
        }

        // Operasional: Dosen dan Staff umum
        $operasionalRoles = ['Dosen', 'Staff'];
        foreach ($operasionalRoles as $rName) {
            $role = Role::where('name', $rName)->first();
            if ($role) {
                $role->givePermissionTo(['dasbor', 'profil-saya', 'logbook-harian', 'pengajuan-dana']);
            }
        }

        // 3. MEMASTIKAN AKUN JARKOMIT ADALAH SUPER ADMIN
        $jarkomit = User::where('email', 'jarkomit@ahmaddahlan.ac.id')->first();
        if ($jarkomit) {
            $jarkomit->assignRole('Super Admin');
        }
    }
}