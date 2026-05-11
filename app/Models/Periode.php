<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Periode extends Model
{
    use HasFactory;

    // Menentukan nama tabel secara eksplisit karena kita menggunakan nama 'periodes'
    protected $table = 'periodes';

    protected $fillable = [
        'nama_periode',
        'tanggal_mulai',
        'tanggal_selesai',
        'status', 
        'is_current',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'is_current' => 'boolean',
    ];

    /**
     * Scope untuk mengambil periode yang sedang aktif.
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    // --- RELASI KE TABEL LAIN ---

    public function logbooks(): HasMany
    {
        return $this->hasMany(Logbook::class, 'periode_id');
    }

    public function workPrograms(): HasMany
    {
        return $this->hasMany(WorkProgram::class, 'periode_id');
    }

    public function fundSubmissions(): HasMany
    {
        return $this->hasMany(FundSubmission::class, 'periode_id');
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(PerformanceIndicator::class, 'periode_id');
    }
}