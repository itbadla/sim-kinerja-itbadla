<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'kode_unit',
        'nama_unit',
        'parent_id',
        'kepala_unit_id',
    ];

    // 1 Unit punya banyak User (Staf)
    public function users()
    {
        return $this->hasMany(User::class);
    }

    // 1 Unit dikepalai oleh 1 User
    public function kepalaUnit()
    {
        return $this->belongsTo(User::class, 'kepala_unit_id');
    }

    // Relasi Hirarki (Sub-Unit)
    public function parent()
    {
        return $this->belongsTo(Unit::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Unit::class, 'parent_id');
    }
}