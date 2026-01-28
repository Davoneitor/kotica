<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Obra extends Model
{
   protected $fillable = [
    'nombre',
    'descripcion',
    'erp_proyecto_id',
    'erp_unidad_negocio_id',
    'erp_unidad_negocio',
];


  public function users()
{
    return $this->belongsToMany(\App\Models\User::class, 'obra_user', 'obra_id', 'user_id')
        ->withTimestamps();
}

}
