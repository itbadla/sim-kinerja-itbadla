<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FundSubmission extends Model
{
    use SoftDeletes;

    protected $table = 'fund_submissions';

    protected $fillable = [
        'user_id',
        'unit_id',
        'work_program_id',
        'periode_id',
        'tipe_pengajuan', 
        'nominal_total',
        'nominal_disetujui',
        'keperluan',
        'file_lampiran',
        'status_pengajuan', 
        'catatan_verifikator',
        'verified_by',     // TAMBAHAN
        'verified_at',     // TAMBAHAN
        'skema_pencairan', 
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function unit(): BelongsTo { return $this->belongsTo(Unit::class); }
    public function workProgram(): BelongsTo { return $this->belongsTo(WorkProgram::class); }
    public function periode(): BelongsTo { return $this->belongsTo(Periode::class, 'periode_id'); }
    
    // Relasi untuk melacak siapa yang memverifikasi
    public function verifikator(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }

    public function disbursements(): HasMany
    {
        return $this->hasMany(FundDisbursement::class, 'fund_submission_id')->orderBy('termin_ke', 'asc');
    }
}