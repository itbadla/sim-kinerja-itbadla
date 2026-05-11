<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Logbook extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'unit_id',
        'work_program_id',
        'periode_id',
        'tanggal',
        'jam_mulai',
        'jam_selesai',
        'kategori',
        'deskripsi_aktivitas',
        'output',
        'file_bukti',
        'link_bukti',
        'status',
        'catatan_verifikator',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'jam_mulai' => 'datetime:H:i',
            'jam_selesai' => 'datetime:H:i',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Logbook ini bagian dari eksekusi Proker mana?
     */
    public function workProgram(): BelongsTo
    {
        return $this->belongsTo(WorkProgram::class);
    }

    /**
     * Siapa yang memverifikasi logbook ini?
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class, 'periode_id');
    }
}