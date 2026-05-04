<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Logbook extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'unit_id',
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

    protected $casts = [
        'tanggal' => 'date',
        'jam_mulai' => 'datetime:H:i',
        'jam_selesai' => 'datetime:H:i',
        'verified_at' => 'datetime',
    ];

    // Logbook ini milik siapa?
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Logbook ini dikerjakan untuk unit mana?
    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    // Siapa yang memverifikasi logbook ini?
    public function verifikator()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}