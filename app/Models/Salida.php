<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Salida extends Model
{
    protected $fillable = [
        'fecha','obra_id','user_id','nombre_cabo',
        'erp_proyecto_id','destino_nombre','estatus'
    ];

    public function items()
    {
        return $this->hasMany(SalidaItem::class);
    }
}
