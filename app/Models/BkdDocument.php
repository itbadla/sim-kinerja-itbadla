<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BkdDocument extends Model
{
    protected $table = 'bkd_documents';

    protected $fillable = [
        'bkd_activity_id',
        'nama_dokumen',
        'jenis_dokumen',
        'file_path',
        'tautan_luar',
        'sister_doc_id',
    ];

    /**
     * Kegiatan BKD induknya
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(BkdActivity::class, 'bkd_activity_id');
    }
}