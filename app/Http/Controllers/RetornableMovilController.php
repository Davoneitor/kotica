<?php

namespace App\Http\Controllers;

use App\Models\MovimientoDetalle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RetornableMovilController extends Controller
{
    /**
     * GET /api/retornables
     * Retornables pendientes de la obra actual, con filtros opcionales.
     */
    public function index(Request $request)
    {
        $obraId   = (int) (auth()->user()?->obra_actual_id ?? 0);
        $qPersona = trim((string) $request->get('persona', ''));
        $qInsumo  = trim((string) $request->get('insumo', ''));

        if (!$obraId) return response()->json([]);

        $rows = DB::table('movimiento_detalles as md')
            ->join('movimientos as m', 'm.id', '=', 'md.movimiento_id')
            ->join('inventarios as i', 'i.id', '=', 'md.inventario_id')
            ->where('md.devolvible', 1)
            ->where('m.obra_id', $obraId)
            ->when($qPersona !== '', fn($q) =>
                $q->where('m.nombre_cabo', 'like', "%{$qPersona}%")
            )
            ->when($qInsumo !== '', fn($q) =>
                $q->where(function ($w) use ($qInsumo) {
                    $w->where('md.descripcion', 'like', "%{$qInsumo}%")
                      ->orWhere('i.insumo_id', 'like', "%{$qInsumo}%");
                })
            )
            ->select([
                'md.id as detalle_id',
                'md.inventario_id',
                'i.insumo_id',
                'md.descripcion',
                'md.unidad',
                'md.cantidad',
                'm.id as movimiento_id',
                'm.nombre_cabo',
                'm.fecha',
                'm.observaciones',
                DB::raw('DATEDIFF(day, m.fecha, GETDATE()) as dias'),
            ])
            ->orderByDesc('m.fecha')
            ->get();

        return response()->json($rows->values());
    }

    /**
     * POST /api/retornables/{detalle}/recuperar
     * Reintegra la cantidad al inventario y quita la marca devolvible.
     */
    public function recuperar(MovimientoDetalle $detalle)
    {
        $obraId = (int) (auth()->user()?->obra_actual_id ?? 0);

        // Verificar que el detalle pertenece a la obra del usuario
        $movimiento = DB::table('movimientos')->where('id', $detalle->movimiento_id)->first();
        if (!$movimiento || (int) $movimiento->obra_id !== $obraId) {
            return response()->json(['ok' => false, 'message' => 'Sin autorización.'], 403);
        }

        if (!$detalle->devolvible) {
            return response()->json(['ok' => false, 'message' => 'Este ítem ya fue recuperado.'], 422);
        }

        DB::transaction(function () use ($detalle) {
            DB::table('inventarios')
                ->where('id', $detalle->inventario_id)
                ->increment('cantidad', $detalle->cantidad);

            $detalle->update(['devolvible' => 0]);
        });

        return response()->json(['ok' => true]);
    }
}
