<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransferenciaEntreObras extends Model
{
    protected $table = 'transferencias_entre_obras';

    protected $fillable = [
        'obra_origen_id',
        'obra_destino_id',
        'user_id',
        'fecha',
        'observaciones',
    ];

    public function obraOrigen()
    {
        return $this->belongsTo(Obra::class, 'obra_origen_id');
    }

    public function obraDestino()
    {
        return $this->belongsTo(Obra::class, 'obra_destino_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function detalles()
    {
        return $this->hasMany(TransferenciaEntreObrasDetalle::class, 'transferencia_id');
    }
}
