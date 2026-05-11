<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OcFiniquito extends Model
{
    protected $table = 'oc_finiquitos';

    protected $fillable = [
        'obra_id',
        'user_id',
        'id_pedido',
        'pedido_det_id',
        'insumo',
        'descripcion',
        'unidad',
        'cantidad_pedida',
        'cantidad_recibida',
        'diferencia',
        'observaciones',
    ];

    protected $casts = [
        'obra_id'            => 'integer',
        'user_id'            => 'integer',
        'id_pedido'          => 'integer',
        'pedido_det_id'      => 'integer',
        'cantidad_pedida'    => 'float',
        'cantidad_recibida'  => 'float',
        'diferencia'         => 'float',
    ];

    public function obra()
    {
        return $this->belongsTo(Obra::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
