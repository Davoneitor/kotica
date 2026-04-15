<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GraficasMovilController extends Controller
{
    public function index(Request $request)
    {
        $user   = Auth::user();
        $obraId = (int) ($user?->obra_actual_id ?? 0);

        if (!$obraId) {
            return response()->json(['familias' => [], 'insumos' => []]);
        }

        $desde = $request->get('desde', '');
        $hasta = $request->get('hasta', '');
        $q     = trim((string) $request->get('q', ''));

        // ── Familias ─────────────────────────────────────────────────────────
        $qFamilias = DB::table('movimiento_detalles as md')
            ->join('movimientos as m', 'md.movimiento_id', '=', 'm.id')
            ->select('md.familia', DB::raw('SUM(md.cantidad) as total'))
            ->where('m.obra_id', $obraId)
            ->whereNotNull('md.familia')
            ->where('md.familia', '<>', '')
            ->groupBy('md.familia')
            ->orderByDesc('total')
            ->limit(10);

        if ($desde) $qFamilias->whereDate('m.fecha', '>=', $desde);
        if ($hasta)  $qFamilias->whereDate('m.fecha', '<=', $hasta);
        if ($q)      $qFamilias->where('md.familia', 'like', "%{$q}%");

        $familias = $qFamilias->get()->map(fn($r) => [
            'familia' => $r->familia,
            'total'   => round((float) $r->total, 2),
        ]);

        // ── Insumos ───────────────────────────────────────────────────────────
        $qInsumos = DB::table('movimiento_detalles as md')
            ->join('movimientos as m', 'md.movimiento_id', '=', 'm.id')
            ->leftJoin('inventarios as i', 'md.inventario_id', '=', 'i.id')
            ->select(
                'md.inventario_id',
                DB::raw('COALESCE(i.insumo_id, CAST(md.inventario_id AS NVARCHAR)) as insumo_id'),
                DB::raw('COALESCE(i.descripcion, md.descripcion) as descripcion'),
                DB::raw('COALESCE(i.unidad, md.unidad) as unidad'),
                'md.familia',
                DB::raw('SUM(md.cantidad) as total')
            )
            ->where('m.obra_id', $obraId)
            ->whereNotNull('md.inventario_id')
            ->groupBy('md.inventario_id', 'i.insumo_id', 'i.descripcion', 'md.descripcion', 'i.unidad', 'md.unidad', 'md.familia')
            ->orderByDesc('total')
            ->limit(10);

        if ($desde) $qInsumos->whereDate('m.fecha', '>=', $desde);
        if ($hasta)  $qInsumos->whereDate('m.fecha', '<=', $hasta);
        if ($q)      $qInsumos->where(function ($w) use ($q) {
            $w->where('i.descripcion', 'like', "%{$q}%")
              ->orWhere('md.descripcion', 'like', "%{$q}%");
        });

        $insumos = $qInsumos->get()->map(fn($r) => [
            'inventario_id' => $r->inventario_id,
            'insumo_id'     => $r->insumo_id,
            'descripcion'   => $r->descripcion,
            'unidad'        => $r->unidad,
            'familia'       => $r->familia,
            'total'         => round((float) $r->total, 2),
        ]);

        // ── Salidas por día (últimos 30 días) ─────────────────────────────────
        $desdeDias = $desde ?: now()->subDays(29)->toDateString();
        $hastaDias = $hasta ?: now()->toDateString();

        $porDia = DB::table('movimientos as m')
            ->join('movimiento_detalles as md', 'md.movimiento_id', '=', 'm.id')
            ->select(DB::raw('CAST(m.fecha AS DATE) as dia'), DB::raw('SUM(md.cantidad) as total'))
            ->where('m.obra_id', $obraId)
            ->whereDate('m.fecha', '>=', $desdeDias)
            ->whereDate('m.fecha', '<=', $hastaDias)
            ->groupBy(DB::raw('CAST(m.fecha AS DATE)'))
            ->orderBy('dia')
            ->get()
            ->map(fn($r) => [
                'dia'   => $r->dia,
                'total' => round((float) $r->total, 2),
            ]);

        return response()->json([
            'familias' => $familias,
            'insumos'  => $insumos,
            'por_dia'  => $porDia,
        ]);
    }
}
