<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalidaCamionEscombro extends Model
{
    protected $table = 'salida_camiones_escombro';

    protected $fillable = [
        'obra_id',
        'user_id',
        'fecha',
        'hora_entrada',
        'hora_salida',
        'tipo_material',
        'placas',
        'metros_cubicos',
        'folio_recibo',
        'foto_vale',
        'foto_camion',
    ];

    protected $casts = [
        'fecha'          => 'date',
        'metros_cubicos' => 'float',
    ];
}
