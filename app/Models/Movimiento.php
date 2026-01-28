<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Movimiento extends Model
{
    protected $table = 'movimientos';

    protected $fillable = [
        'obra_id',
        'user_id',
        'fecha',
        'destino',
        'nombre_cabo',
        'observaciones',
        'estatus',
        'firma_recibe_path',
    ];

    public function detalles()
    {
        return $this->hasMany(\App\Models\MovimientoDetalle::class, 'movimiento_id');
    }
}
