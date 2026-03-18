<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'is_multiobra',
        'solo_explore',

        // ✅ para poder guardar/cambiar la obra actual con update()/create()
        'obra_actual_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
            'is_admin'      => 'boolean',
            'is_multiobra'  => 'integer',
            'solo_explore'  => 'boolean',

            // ✅ cast para que siempre sea int
            'obra_actual_id' => 'integer',
        ];
    }

    public function obras()
    {
        return $this->belongsToMany(\App\Models\Obra::class, 'obra_user', 'user_id', 'obra_id')
            ->withTimestamps();
    }

    public function obraActual()
    {
        return $this->belongsTo(\App\Models\Obra::class, 'obra_actual_id');
    }
}
