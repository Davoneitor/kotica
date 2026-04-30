<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OcRecepcion extends Model
{
    protected $table = 'oc_recepciones';

    protected $fillable = [
        'obra_id',
        'user_id',
        'id_pedido',
        'pedido_det_id',
        'insumo',
        'descripcion',
        'unidad',
        'fecha_oc',
        'fecha_recibido',
        'cantidad_llego',
        'precio_unitario',
        'foto_path',
        'tipo',
        'observaciones',
        'familia',
        'subfamilia',
        'revertida_at',
        'revertida_por',
        'motivo_reversion',
    ];

    protected $casts = [
        'obra_id'          => 'integer',
        'user_id'          => 'integer',
        'pedido_det_id'    => 'integer',
        'cantidad_llego'   => 'float',
        'precio_unitario'  => 'float',
        'fecha_oc'         => 'date',
        'fecha_recibido'   => 'datetime',
        'revertida_at'     => 'datetime',
        'revertida_por'    => 'integer',
    ];

    public $timestamps = true;

    public function obra()
    {
        return $this->belongsTo(\App\Models\Obra::class, 'obra_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function revertidaPor()
    {
        return $this->belongsTo(\App\Models\User::class, 'revertida_por');
    }

    public function estaRevertida(): bool
    {
        return $this->revertida_at !== null;
    }
}
