<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AjusteSalida extends Model
{
    protected $table = 'ajustes_salida';

    protected $fillable = [
        'movimiento_id',
        'movimiento_detalle_id',
        'inventario_id',
        'user_id',
        'descripcion',
        'unidad',
        'cantidad_devuelta',
        'observaciones',
    ];

    public function movimiento()
    {
        return $this->belongsTo(Movimiento::class, 'movimiento_id');
    }

    public function detalle()
    {
        return $this->belongsTo(MovimientoDetalle::class, 'movimiento_detalle_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
