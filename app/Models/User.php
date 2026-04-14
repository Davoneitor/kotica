<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        "name",
        "email",
        "password",
        "is_admin",
        "is_multiobra",
        "solo_explore",
        "puede_editar_desc_auxiliar",
        "obra_actual_id",
    ];

    protected $hidden = [
        "password",
        "remember_token",
    ];

    protected function casts(): array
    {
        return [
            "email_verified_at"          => "datetime",
            "password"                   => "hashed",
            "is_admin"                   => "boolean",
            "is_multiobra"               => "integer",
            "solo_explore"               => "boolean",
            "puede_editar_desc_auxiliar" => "boolean",
            "obra_actual_id"             => "integer",
        ];
    }

    public function obras()
    {
        return $this->belongsToMany(\App\Models\Obra::class, "obra_user", "user_id", "obra_id")
            ->withTimestamps();
    }

    public function obraActual()
    {
        return $this->belongsTo(\App\Models\Obra::class, "obra_actual_id");
    }
}
