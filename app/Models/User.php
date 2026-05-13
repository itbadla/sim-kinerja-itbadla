<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    /**
     * Atribut yang dapat diisi (Mass Assignable).
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
    ];

    /**
     * Atribut yang disembunyikan saat serialisasi.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casting tipe data.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // =========================================================
    // ACCESSOR
    // =========================================================

    /**
     * Accessor untuk Unit Utama (Homebase).
     * Memungkinkan memanggil $user->unit untuk mendapatkan unit pertama.
     */
    protected function unit(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->units->first(),
        );
    }

    // =========================================================
    // RELASI
    // =========================================================

    /**
     * Relasi ke Unit Kerja melalui tabel pivot unit_user.
     * Menggunakan model pivot kustom UnitUser untuk mendukung relasi jabatan.
     */
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'unit_user')
            ->using(UnitUser::class) // Menggunakan model pivot kustom
            ->withPivot('position_id', 'is_active')
            ->withTimestamps();
    }

    /**
     * Relasi ke Unit yang dipimpin oleh User ini.
     */
    public function ledUnits(): HasMany
    {
        return $this->hasMany(Unit::class, 'kepala_unit_id');
    }

    public function fundSubmissions(): HasMany
    {
        return $this->hasMany(FundSubmission::class);
    }

    public function logbooks(): HasMany
    {
        return $this->hasMany(Logbook::class);
    }

    // =========================================================
    // LOGIKA CERDAS & SCOPE
    // =========================================================

    /**
     * Scope untuk mencari bawahan (anggota unit yang dipimpin secara hirarki).
     */
    public function scopeBawahan(Builder $query, User $atasan)
    {
        // Ambil semua unit yang dipimpin beserta anak-anaknya secara rekursif
        $unitIds = Unit::where('kepala_unit_id', $atasan->id)->get()->flatMap(function($unit) {
            return array_merge([$unit->id], $unit->getAllChildrenIds());
        })->unique()->toArray();

        return $query->whereHas('units', function($q) use ($unitIds) {
            $q->whereIn('units.id', $unitIds);
        })->where('users.id', '!=', $atasan->id); // Kecualikan diri sendiri
    }

    /**
     * Sinkronisasi Role Spatie berdasarkan Jabatan di tabel unit_user.
     * Fungsi ini menggabungkan Role Bawaan Jabatan + Role Manual (seperti Super Admin).
     */
    public function syncRolesFromPositions()
    {
        // 1. Ambil ID Role dari semua jabatan yang sedang dipegang user di tabel pivot
        $roleIdsFromPositions = DB::table('unit_user')
            ->join('positions', 'unit_user.position_id', '=', 'positions.id')
            ->where('unit_user.user_id', $this->id)
            ->whereNotNull('positions.role_id')
            ->pluck('positions.role_id')
            ->toArray();

        // 2. Ambil nama role-role tersebut dari tabel Spatie roles
        $rolesFromPositions = Role::whereIn('id', $roleIdsFromPositions)->pluck('name')->toArray();

        // 3. Ambil daftar semua nama role yang terikat dengan Master Jabatan mana pun
        // Ini digunakan untuk membedakan mana role "jabatan" dan mana role "manual"
        $allPositionRoleNames = Role::whereIn('id', function($query) {
                $query->select('role_id')->from('positions')->whereNotNull('role_id');
            })->pluck('name')->toArray();

        // 4. Identifikasi Role Manual yang sudah dimiliki user 
        // (Role yang tidak ada di daftar Master Jabatan, misal: 'Super Admin', 'Panitia')
        $manualRoles = $this->roles()
            ->whereNotIn('name', $allPositionRoleNames)
            ->pluck('name')
            ->toArray();

        // 5. Gabungkan keduanya dan sinkronisasi tanpa menghapus role manual
        $finalRoles = array_unique(array_merge($rolesFromPositions, $manualRoles));
        
        return $this->syncRoles($finalRoles);
    }

    public function bkdActivities()
    {
        return $this->hasMany(BkdActivity::class, 'user_id');
    }
}