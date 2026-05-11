<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Position extends Model
{
    //  
    protected $fillable = [
        'nama_jabatan', 
        'level_otoritas', 
        'kategori',
        'role_id'
    ];

    /**
     * Relasi ke User melalui tabel pivot unit_user.
     * Satu jabatan bisa dimiliki oleh banyak user di berbagai unit.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'unit_user')
            ->withPivot('unit_id', 'is_active')
            ->withTimestamps();
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
