<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Cargar nombres de obras para las observaciones
        $obras = DB::table('obras')->pluck('nombre', 'id');

        // Lookup de familia/subfamilia desde inventarios (cualquier obra)
        $familias = DB::table('inventarios')
            ->whereNotNull('insumo_id')
            ->where('insumo_id', '!=', '')
            ->whereNotNull('familia')
            ->where('familia', '!=', '')
            ->select('insumo_id', 'familia', 'subfamilia')
            ->get()
            ->keyBy('insumo_id');

        // Traer todos los detalles de transferencias con info del encabezado
        $detalles = DB::table('transferencias_entre_obras_detalle as d')
            ->join('transferencias_entre_obras as t', 't.id', '=', 'd.transferencia_id')
            ->select(
                'd.id as detalle_id',
                'd.transferencia_id',
                'd.insumo_id',
                'd.descripcion',
                'd.unidad',
                'd.cantidad',
                't.obra_destino_id',
                't.user_id',
                't.fecha',
                't.created_at as transferencia_created_at',
                't.observaciones as transfer_obs',
                't.obra_origen_id'
            )
            ->get();

        $now = now()->toDateTimeString();

        foreach ($detalles as $d) {
            $obraOrigenNombre = $obras[$d->obra_origen_id] ?? 'obra #' . $d->obra_origen_id;
            $observaciones    = 'Transferencia #' . $d->transferencia_id . ' desde ' . $obraOrigenNombre;
            if (!empty($d->transfer_obs)) {
                $observaciones .= ' — ' . $d->transfer_obs;
            }

            $inv       = $familias[$d->insumo_id] ?? null;
            $familia   = $inv ? ($inv->familia    ?? 'SIN FAMILIA')    : 'SIN FAMILIA';
            $subfamilia= $inv ? ($inv->subfamilia ?? 'SIN SUBFAMILIA') : 'SIN SUBFAMILIA';

            // Evitar duplicados: ya existe un registro para este detalle si
            // observaciones contiene "Transferencia #X desde" y obra_id + insumo coinciden.
            $existe = DB::table('oc_recepciones')
                ->where('obra_id', $d->obra_destino_id)
                ->where('tipo', 'transferencia')
                ->where('insumo', $d->insumo_id)
                ->where('observaciones', 'like', 'Transferencia #' . $d->transferencia_id . ' desde %')
                ->exists();

            if ($existe) {
                continue;
            }

            DB::table('oc_recepciones')->insert([
                'obra_id'         => $d->obra_destino_id,
                'user_id'         => $d->user_id,
                'id_pedido'       => 0,
                'pedido_det_id'   => 0,
                'insumo'          => (string) ($d->insumo_id ?? ''),
                'descripcion'     => $d->descripcion,
                'unidad'          => (string) ($d->unidad ?? ''),
                'fecha_oc'        => $d->fecha,
                'fecha_recibido'  => $d->transferencia_created_at,
                'cantidad_llego'  => (float) $d->cantidad,
                'precio_unitario' => null,
                'foto_path'       => '',
                'tipo'            => 'transferencia',
                'observaciones'   => $observaciones,
                'familia'         => $familia,
                'subfamilia'      => $subfamilia,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Eliminar solo los registros insertados por este backfill (tipo=transferencia sin foto)
        // que correspondan a transferencias creadas antes de 2026-04-29
        DB::table('oc_recepciones')
            ->where('tipo', 'transferencia')
            ->where('created_at', '<', '2026-04-29 00:00:00')
            ->delete();
    }
};
