<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'google_id', 'email_verified_at', 'unit_id', 'jabatan'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // --- RELASI ---

    // 1 User berada di 1 Unit
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    // 1 User punya banyak Logbook (sebagai pembuat)
    public function logbooks()
    {
        return $this->hasMany(Logbook::class, 'user_id');
    }

    // 1 User (sebagai atasan) memverifikasi banyak Logbook
    public function verifiedLogbooks()
    {
        return $this->hasMany(Logbook::class, 'verified_by');
    }
}
