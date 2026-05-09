<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TahunAnggaran extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang didefinisikan secara eksplisit.
     */
    protected $table = 'tahun_anggaran';

    /**
     * Atribut yang dapat diisi massal.
     */
    protected $fillable = [
        'nama_tahun',
        'tanggal_mulai',
        'tanggal_selesai',
        'status', // planning, active, closed
        'is_current',
    ];

    /**
     * Casting tipe data agar lebih mudah dikelola di Carbon/Boolean.
     */
    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'is_current' => 'boolean',
    ];

    /**
     * SCOPE: Mengambil tahun anggaran yang sedang aktif (is_current = true).
     * Contoh penggunaan: TahunAnggaran::current()->first();
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /**
     * RELASI: Menghubungkan ke semua Program Kerja (Proker) di tahun ini.
     */
    public function workPrograms(): HasMany
    {
        return $this->hasMany(WorkProgram::class, 'tahun_anggaran_id');
    }

    /**
     * RELASI: Menghubungkan ke semua Catatan Kinerja (Logbook) di tahun ini.
     */
    public function logbooks(): HasMany
    {
        return $this->hasMany(Logbook::class, 'tahun_anggaran_id');
    }

    /**
     * RELASI: Menghubungkan ke target Indikator Kinerja (IKU/IKT) tahun ini.
     */
    public function indicators(): HasMany
    {
        return $this->hasMany(PerformanceIndicator::class, 'tahun_anggaran_id');
    }

    /**
     * RELASI: Menghubungkan ke semua transaksi Pengajuan Dana tahun ini.
     */
    public function fundSubmissions(): HasMany
    {
        return $this->hasMany(FundSubmission::class, 'tahun_anggaran_id');
    }

    /**
     * HELPER: Mengecek apakah periode ini sudah dikunci (Arsip).
     */
    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * HELPER: Mengecek apakah periode ini dalam tahap perencanaan.
     */
    public function isPlanning(): bool
    {
        return $this->status === 'planning';
    }
}