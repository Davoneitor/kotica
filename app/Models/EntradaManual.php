<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntradaManual extends Model
{
    protected $table = 'entradas_manuales';

    protected $fillable = [
        'obra_id',
        'user_id',
        'insumo_id',
        'descripcion',
        'unidad',
        'proveedor',
        'cantidad',
        'costo_unitario',
        'fecha_entrada',
        'observaciones',
        'familia',
        'subfamilia',
    ];

    protected $casts = [
        'cantidad'       => 'float',
        'costo_unitario' => 'float',
        'fecha_entrada'  => 'date',
    ];

    public function obra()
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
