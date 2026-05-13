<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BkdActivity extends Model
{
    use SoftDeletes;

    protected $table = 'bkd_activities';

    protected $fillable = [
        'user_id',
        'periode_id',
        'sister_id',
        'kategori_tridharma',
        'rubrik_kegiatan_id',
        'judul_kegiatan',
        'tanggal_mulai',
        'tanggal_selesai',
        'deskripsi',
        'sks_beban',
        'sync_status',
        'last_synced_at',
        'status_internal',
        'asesor_id',
        'catatan_asesor',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'last_synced_at' => 'datetime',
        'sks_beban' => 'float',
    ];

    /**
     * Dosen pemilik/pelapor kegiatan ini
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Semester/Periode kegiatan
     */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class, 'periode_id');
    }

    /**
     * Asesor Internal yang memverifikasi
     */
    public function asesor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asesor_id');
    }

    /**
     * Anggota tim dalam kegiatan ini
     */
    public function members(): HasMany
    {
        return $this->hasMany(BkdMember::class, 'bkd_activity_id');
    }

    /**
     * Dokumen lampiran pendukung kegiatan
     */
    public function documents(): HasMany
    {
        return $this->hasMany(BkdDocument::class, 'bkd_activity_id');
    }
}