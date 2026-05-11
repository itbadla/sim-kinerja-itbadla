<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PerformanceIndicator extends Model
{
    protected $fillable = [
        'periode_id',
        'kode_indikator',
        'nama_indikator',
        'kategori',
    ];

    /**
     * Relasi ke Program Kerja yang mendukung indikator ini.
     */
    public function workPrograms(): BelongsToMany
    {
        return $this->belongsToMany(WorkProgram::class, 'work_program_indicators')
            ->withPivot('target_angka', 'satuan_target');
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class, 'periode_id');
    }
}