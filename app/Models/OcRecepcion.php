<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OcRecepcion extends Model
{
    protected $table = 'oc_recepciones';

    /**
     * Campos que se pueden asignar en masa
     */
    protected $fillable = [
        'obra_id',
        'user_id',
        'id_pedido',        // nÃºmero / cÃ³digo de OC
        'pedido_det_id',
        'insumo',
        'descripcion',
        'unidad',
        'fecha_oc',
        'fecha_recibido',
        'cantidad_llego',
        'precio_unitario',
        'foto_path',
    ];

    /**
     * Casts para que Laravel trate correctamente los tipos
     * (MUY importante para fechas y nÃºmeros)
     */
    protected $casts = [
        'obra_id'          => 'integer',
        'user_id'          => 'integer',
        'pedido_det_id'    => 'integer',
        'cantidad_llego'   => 'float',
        'precio_unitario'  => 'float',

        // ðŸ‘‡ claves para que optional(...)->format() funcione
        'fecha_oc'         => 'date',
        'fecha_recibido'   => 'datetime',
    ];

    /**
     * Opcional: si tu tabla NO tiene deleted_at
     * (por defecto Laravel no lo usa, esto es solo aclaratorio)
     */
    public $timestamps = true;

    /**
     * Relaciones (opcional, pero recomendado)
     */
    public function obra()
    {
        return $this->belongsTo(\App\Models\Obra::class, 'obra_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
