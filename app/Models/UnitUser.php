<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UnitUser extends Pivot
{
    protected $table = 'unit_user';

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}