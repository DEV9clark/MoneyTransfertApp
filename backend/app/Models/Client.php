<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Client (expéditeur). Il peut être créé par un agent au guichet, ou s'inscrire
 * lui-même dans l'application mobile (téléphone vérifié par SMS + code PIN).
 */
class Client extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'email',
        'phone',
        'address',
        'country_id',
        'id_type',
        'id_number',
        'pin_hash',
        'phone_verified_at',
        'pin_failed_attempts',
        'pin_locked_until',
        'last_login_at',
        'is_active',
    ];

    protected $hidden = [
        'pin_hash',
        'pin_failed_attempts',
        'pin_locked_until',
        'remember_token',
    ];

    protected $casts = [
        'phone_verified_at' => 'datetime',
        'pin_locked_until' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function hasAppAccess(): bool
    {
        return $this->pin_hash !== null && $this->phone_verified_at !== null;
    }

    public function isPinLocked(): bool
    {
        return $this->pin_locked_until !== null && $this->pin_locked_until->isFuture();
    }

    /** Les clients n'ont pas de mot de passe web. */
    public function getAuthPassword()
    {
        return $this->pin_hash;
    }

    public function getRememberTokenName()
    {
        return null;
    }
}
