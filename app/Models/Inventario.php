<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventario extends Model
{
    protected $table = 'inventarios';

  protected $fillable = [
    'insumo_id',
    'familia',
    'subfamilia',
    'descripcion',
    'unidad',
    'obra_id',
    'proveedor',
    'cantidad',
    'cantidad_teorica',
    'en_espera',
    'costo_promedio',
    'destino',
    'devolvible',
];


    public function obra()
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }
}
