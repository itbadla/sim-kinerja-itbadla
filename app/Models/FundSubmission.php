<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundSubmission extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'unit_id',
        'work_program_id',
        'periode_id',
        'tipe_pengajuan',
        'nominal',
        'keperluan',
        'file_lampiran',
        'status',
        'catatan_verifikator',
        'status_lpj',
        'nominal_realisasi',
        'file_lpj',
        'waktu_pengembalian',
        'catatan_pengembalian',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Pengajuan dana ini merujuk ke Proker mana?
     */
    public function workProgram(): BelongsTo
    {
        return $this->belongsTo(WorkProgram::class);
    }
    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class, 'periode_id');
    }
}