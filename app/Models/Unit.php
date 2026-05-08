<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Unit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'kode_unit',
        'nama_unit',
        'parent_id',
        'kepala_unit_id',
    ];

    /**
     * Relasi ke User yang menjadi anggota di unit ini (Tabel Pivot).
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'unit_user')
            ->withPivot('position_id', 'is_active') // Ganti jabatan_di_unit ke position_id
            ->withTimestamps();
    }
    /**
     * Relasi ke User yang menjadi Kepala Unit (Verifikator utama).
     */
    public function kepalaUnit(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kepala_unit_id');
    }

    /**
     * Relasi ke Unit Induk (Hirarki ke atas).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'parent_id');
    }

    /**
     * Relasi ke Sub-Unit di bawahnya (Hirarki ke bawah).
     */
    public function children(): HasMany
    {
        return $this->hasMany(Unit::class, 'parent_id');
    }

    /**
     * Relasi ke Program Kerja (Raker) milik unit ini.
     */
    public function workPrograms(): HasMany
    {
        return $this->hasMany(WorkProgram::class);
    }

    /**
     * Relasi ke semua pengajuan dana atas nama unit ini.
     */
    public function fundSubmissions(): HasMany
    {
        return $this->hasMany(FundSubmission::class);
    }

    /**
     * Relasi ke semua logbook aktivitas harian di unit ini.
     */
    public function logbooks(): HasMany
    {
        return $this->hasMany(Logbook::class);
    }

    /**
     * Mengambil semua ID sub-unit secara rekursif hingga level terdalam.
     * Digunakan oleh User::scopeBawahan untuk menentukan cakupan verifikasi pimpinan.
     */
    public function getAllChildrenIds(): array
    {
        $ids = [];

        // Gunakan eager loading 'children' jika memungkinkan untuk efisiensi
        foreach ($this->children as $child) {
            // Masukkan ID anak langsung
            $ids[] = $child->id;
            
            // Masukkan ID dari cucu, cicit, dst secara rekursif
            $ids = array_merge($ids, $child->getAllChildrenIds());
        }

        return array_unique($ids);
    }

    /**
     * Alias untuk getAllChildrenIds (untuk konsistensi penamaan di masa depan).
     */
    public function getAllDescendantIds(): array
    {
        return $this->getAllChildrenIds();
    }
}