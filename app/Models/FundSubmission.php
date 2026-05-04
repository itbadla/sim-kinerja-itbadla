<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FundSubmission extends Model
{
    use HasFactory, SoftDeletes;

    // Kolom yang boleh diisi
    protected $fillable = [
        'user_id',
        'unit_id',
        'tipe_pengajuan',
        'nominal',
        'keperluan',
        'file_lampiran',
        'status',
        'catatan_verifikator',
        // Tambahan untuk LPJ:
        'nominal_realisasi',
        'file_lpj',
        'status_lpj',
        // pengembalian dana
        'waktu_pengembalian',
        'catatan_pengembalian',
    ];

    // Casting tipe data agar lebih mudah diakses di frontend
    protected $casts = [
        'nominal' => 'decimal:2',
        'nominal_realisasi' => 'decimal:2', // Tambahkan ini juga
        'waktu_pengembalian' => 'datetime',
    ];

    // Relasi ke User pengaju
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relasi ke Unit terkait pengajuan
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}