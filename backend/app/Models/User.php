<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone_number',
        'country',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /** Portefeuille de float de l'agent (hors comptes de commissions). */
    public function wallet()
    {
        return $this->hasOne(Wallet::class)->where('type', Wallet::TYPE_AGENT);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Un agent ne voit que lui-même ; un admin voit tout le monde. */
    public function scopeVisibleTo($query, $user = null)
    {
        // Lighthouse (GraphQL) passe ses arguments en tableau : on retombe alors sur l'utilisateur connecté.
        $user = $user instanceof User ? $user : auth()->user();
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        return $user->isAdmin() ? $query : $query->where('id', $user->id);
    }
}
