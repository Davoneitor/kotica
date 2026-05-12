<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Familia extends Model
{
    protected $table = 'familias';

    protected $fillable = ['familia', 'subfamilia'];

    public static function toSelectArray(): array
    {
        return static::orderBy('familia')->orderBy('subfamilia')->get()
            ->groupBy('familia')
            ->map(fn($g) => $g->pluck('subfamilia')->values()->toArray())
            ->toArray();
    }

    public static function registrarSiNuevo(string $familia, string $subfamilia): void
    {
        if ($familia === '' || $subfamilia === '') {
            return;
        }

        static::firstOrCreate(
            ['familia' => $familia, 'subfamilia' => $subfamilia]
        );
    }
}
