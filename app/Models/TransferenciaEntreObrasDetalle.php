<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransferenciaEntreObrasDetalle extends Model
{
    protected $table = 'transferencias_entre_obras_detalle';

    protected $fillable = [
        'transferencia_id',
        'insumo_id',
        'descripcion',
        'unidad',
        'cantidad',
        'origen_stock_antes',
        'origen_stock_despues',
        'destino_stock_antes',
        'destino_stock_despues',
    ];

    public function transferencia()
    {
        return $this->belongsTo(TransferenciaEntreObras::class, 'transferencia_id');
    }
}
