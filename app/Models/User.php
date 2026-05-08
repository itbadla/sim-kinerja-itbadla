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

    /**
     * Accessor untuk Unit Tunggal (Homebase)
     * Memungkinkan Anda memanggil $user->unit (tanpa s).
     * Ini akan mengambil unit pertama dari daftar unit user.
     */
    protected function unit(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->units->first(),
        );
    }

    /**
     * Relasi Utama ke Unit (Many-to-Many via unit_user).
     * PERBAIKAN: Menggunakan position_id sesuai skema DB baru
     */
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'unit_user')
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

    /**
     * Relasi ke pengajuan dana yang dibuat oleh User.
     */
    public function fundSubmissions(): HasMany
    {
        return $this->hasMany(FundSubmission::class);
    }

    /**
     * Relasi ke logbook kinerja harian User.
     */
    public function logbooks(): HasMany
    {
        return $this->hasMany(Logbook::class);
    }

    /**
     * Scope untuk mencari bawahan dari atasan tertentu.
     */
    public function scopeBawahan(Builder $query, User $atasan)
    {
        $unitIds = Unit::where('kepala_unit_id', $atasan->id)->get()->map(function($unit) {
            return array_merge([$unit->id], $unit->getAllChildrenIds());
        })->flatten()->unique()->toArray();

        return $query->whereHas('units', function($q) use ($unitIds) {
            $q->whereIn('units.id', $unitIds);
        })->where('users.id', '!=', $atasan->id); // Jangan tampilkan diri sendiri
    }
}