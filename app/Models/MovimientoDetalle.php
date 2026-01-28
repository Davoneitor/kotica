<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimientoDetalle extends Model
{
    protected $table = 'movimiento_detalles';

    protected $fillable = [
        'movimiento_id',
        'inventario_id',
        'familia',
        'subfamilia',
        'descripcion',
        'unidad',
        'cantidad',
        'devolvible',

        // ✅ columnas reales en tu tabla
        'clasificacion',     // nivel (S1/L7/etc)
        'clasificacion_d',   // departamento (D1..D8 o NULL)
    ];

    public $timestamps = true;

    public function movimiento()
    {
        return $this->belongsTo(\App\Models\Movimiento::class, 'movimiento_id');
    }
}
