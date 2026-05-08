<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkProgram extends Model
{
    protected $fillable = [
        'unit_id',
        'nama_proker',
        'deskripsi',
        'tahun_anggaran',
        'anggaran_rencana',
        'status',
    ];

    /**
     * Proker ini milik Unit mana?
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Relasi ke IKU/IKT yang didukung oleh Proker ini.
     */
    public function indicators(): BelongsToMany
    {
        return $this->belongsToMany(PerformanceIndicator::class, 'work_program_indicators', 'work_program_id', 'indicator_id')
            ->withPivot('target_angka', 'satuan_target');
    }

    /**
     * Daftar pengajuan dana yang terkait dengan Proker ini.
     */
    public function fundSubmissions(): HasMany
    {
        return $this->hasMany(FundSubmission::class);
    }

    /**
     * Aktivitas harian (logbook) yang berkontribusi pada Proker ini.
     */
    public function logbooks(): HasMany
    {
        return $this->hasMany(Logbook::class);
    }
}