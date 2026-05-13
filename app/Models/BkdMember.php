<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BkdMember extends Model
{
    protected $table = 'bkd_members';

    protected $fillable = [
        'bkd_activity_id',
        'user_id',
        'nama_anggota',
        'peran',
        'is_aktif',
    ];

    protected $casts = [
        'is_aktif' => 'boolean',
    ];

    /**
     * Kegiatan BKD induknya
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(BkdActivity::class, 'bkd_activity_id');
    }

    /**
     * Link ke akun user internal kampus (jika ada)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}