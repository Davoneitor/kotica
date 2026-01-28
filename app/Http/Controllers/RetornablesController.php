<?php

namespace App\Http\Controllers;

use App\Models\MovimientoDetalle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RetornablesController extends Controller
{
    public function index(Request $request)
    {
        $obraId = auth()->user()->obra_actual_id;

        // 🔎 filtros
        $qPersona = trim((string) $request->get('persona', ''));
        $qInsumo  = trim((string) $request->get('insumo', ''));
        $from     = $request->get('from');
        $to       = $request->get('to');

        $retornables = DB::table('movimiento_detalles as md')
            ->join('movimientos as m', 'm.id', '=', 'md.movimiento_id')
            ->join('inventarios as i', 'i.id', '=', 'md.inventario_id')
            ->where('md.devolvible', 1)
            ->where('m.obra_id', $obraId)

            // 👤 quién se lo llevó
            ->when($qPersona !== '', function ($q) use ($qPersona) {
                $q->where('m.nombre_cabo', 'like', "%{$qPersona}%");
            })

            // 🔧 insumo / descripción
            ->when($qInsumo !== '', function ($q) use ($qInsumo) {
                $q->where(function ($w) use ($qInsumo) {
                    $w->where('md.descripcion', 'like', "%{$qInsumo}%")
                      ->orWhere('i.insumo_id', 'like', "%{$qInsumo}%")
                      ->orWhere('md.inventario_id', 'like', "%{$qInsumo}%");
                });
            })

            // 📅 rango de fechas (SIN hora)
            ->when($from, fn($q) => $q->whereDate('m.fecha', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('m.fecha', '<=', $to))

            ->select([
                'md.id as detalle_id',
                'md.inventario_id',
                'i.insumo_id',
                'md.descripcion',
                'md.unidad',
                'md.cantidad',
                'm.nombre_cabo',
                'm.fecha',

                // ✅ días exactos (ENTEROS, sin decimales)
                DB::raw('DATEDIFF(day, m.fecha, GETDATE()) as dias'),
            ])
            ->orderByDesc('m.fecha')
            ->get();

        return view('retornables.retornable', compact(
    'retornables',
    'qPersona',
    'qInsumo',
    'from',
    'to'
));

    }

    /**
     * Recuperar insumo (reintegra al inventario)
     */
    public function recuperar(MovimientoDetalle $detalle)
    {
        DB::transaction(function () use ($detalle) {
            // 1) regresar cantidad al inventario
            DB::table('inventarios')
                ->where('id', $detalle->inventario_id)
                ->increment('cantidad', $detalle->cantidad);

            // 2) marcar como ya recuperado
            $detalle->update([
                'devolvible' => 0,
            ]);
        });

        return back()->with('success', 'Insumo recuperado correctamente.');
    }
}
