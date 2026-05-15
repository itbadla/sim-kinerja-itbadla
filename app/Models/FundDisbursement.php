<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundDisbursement extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'fund_submission_id',
        'termin_ke',
        'nominal_cair',
        'status_cair',
        'tanggal_cair',
        'bukti_transfer_kampus',
        'cair_processed_by',   // TAMBAHAN
        'status_lpj',
        'nominal_realisasi',
        'file_lpj',
        'catatan_revisi_lpj',
        'lpj_verified_by',     // TAMBAHAN
        'nominal_kembali',
        'bukti_pengembalian',
        'waktu_pengembalian',
        'status_pengembalian',
    ];

    protected $casts = [
        'tanggal_cair' => 'date',
        'waktu_pengembalian' => 'datetime',
        'nominal_cair' => 'float',
        'nominal_realisasi' => 'float',
        'nominal_kembali' => 'float',
    ];

    public function submission(): BelongsTo { return $this->belongsTo(FundSubmission::class, 'fund_submission_id'); }
    
    // Relasi Pelacakan Aktor Keuangan
    public function pencair(): BelongsTo { return $this->belongsTo(User::class, 'cair_processed_by'); }
    public function verifikatorLpj(): BelongsTo { return $this->belongsTo(User::class, 'lpj_verified_by'); }
}