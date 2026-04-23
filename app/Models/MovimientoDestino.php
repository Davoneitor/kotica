<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimientoDestino extends Model
{
    protected $table = 'movimiento_destinos';

    protected $fillable = [
        'detalle_id',
        'cantidad',
        'nivel',
        'departamento',
    ];

    public function detalle()
    {
        return $this->belongsTo(MovimientoDetalle::class, 'detalle_id');
    }
}
