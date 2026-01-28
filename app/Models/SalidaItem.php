<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalidaItem extends Model
{
    

    protected $fillable = [
  'movimiento_id','inventario_id','insumo_id','familia','subfamilia',
  'descripcion','unidad','cantidad','devolvible','clasificacion','clasificacion_d'
];
}
