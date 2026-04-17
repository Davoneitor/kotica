<?php

namespace App\Http\Controllers;

use App\Models\Inventario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HistorialController extends Controller
{
    public function index($id)
    {
        $user   = Auth::user();
        $obraId = (int) ($user?->obra_actual_id ?? 0);

        $inv = Inventario::findOrFail($id);

        if ($obraId && (int) $inv->obra_id !== $obraId) {
            return response()->json(['message' => 'Sin acceso.'], 403);
        }

        $existenciaActual = (float) $inv->cantidad;

        // ── Entradas (oc_recepciones) ──────────────────────────────────────────
        $entradas = DB::table('oc_recepciones as r')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.obra_id', $inv->obra_id)
            ->where('r.insumo', $inv->insumo_id)
            ->orderBy('r.fecha_recibido')
            ->get([
                'r.id', 'r.id_pedido', 'r.cantidad_llego', 'r.unidad',
                'r.fecha_recibido as fecha', 'r.precio_unitario',
                DB::raw("u.name as usuario"),
            ]);

        // ── Salidas (movimiento_detalles) ──────────────────────────────────────
        $salidas = DB::table('movimiento_detalles as md')
            ->join('movimientos as m', 'm.id', '=', 'md.movimiento_id')
            ->leftJoin('users as u', 'u.id', '=', 'm.user_id')
            ->where('md.inventario_id', $inv->id)
            ->orderBy('m.fecha')
            ->get([
                'md.id', 'md.movimiento_id', 'md.cantidad', 'md.unidad',
                'm.fecha', 'm.destino', 'm.nombre_cabo',
                DB::raw("u.name as usuario"),
            ]);

        // ── Devoluciones (ajustes_salida) ──────────────────────────────────────
        $devoluciones = DB::table('ajustes_salida as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->where('a.inventario_id', $inv->id)
            ->orderBy('a.created_at')
            ->get([
                'a.id', 'a.movimiento_id', 'a.cantidad_devuelta',
                'a.observaciones', 'a.created_at as fecha',
                DB::raw("u.name as usuario"),
            ]);

        // ── Resumen ────────────────────────────────────────────────────────────
        $totalEntradas     = (float) $entradas->sum('cantidad_llego');
        $totalSalidas      = (float) $salidas->sum('cantidad');
        $totalDevoluciones = (float) $devoluciones->sum('cantidad_devuelta');
        $netoCalculado     = $totalEntradas + $totalDevoluciones - $totalSalidas;
        $diff              = $netoCalculado - $existenciaActual;

        if (abs($diff) <= 0.01) {
            $cuadre = 'ok';
        } elseif ($existenciaActual > $netoCalculado) {
            $cuadre = 'warning';   // más stock del registrado — stock inicial sin OC
        } else {
            $cuadre = 'error';     // menos stock del que debería haber
        }

        // ── Eventos (timeline) ────────────────────────────────────────────────
        $eventos = collect();

        // Creación
        $eventos->push([
            'tipo'    => 'creacion',
            'fecha'   => $inv->created_at?->toIso8601String() ?? '',
            'titulo'  => 'Insumo dado de alta en el sistema',
            'detalle' => $inv->descripcion,
            'usuario' => null,
            'delta'   => 0.0,
        ]);

        // Entradas
        foreach ($entradas as $e) {
            $ocLabel = (int)($e->id_pedido ?? 0) > 0
                ? 'OC #' . $e->id_pedido
                : 'Stock inicial importado';
            $detalle = round((float) $e->cantidad_llego, 2) . ' ' . ($e->unidad ?? '');
            if (!empty($e->precio_unitario) && (float)$e->precio_unitario > 0) {
                $detalle .= ' — PU $' . number_format((float) $e->precio_unitario, 2);
            }
            $eventos->push([
                'tipo'    => 'entrada',
                'fecha'   => (string) $e->fecha,
                'titulo'  => 'Recepción · ' . $ocLabel,
                'detalle' => $detalle,
                'usuario' => $e->usuario,
                'delta'   => (float) $e->cantidad_llego,
            ]);
        }

        // Salidas
        foreach ($salidas as $s) {
            $detalle = round((float) $s->cantidad, 2) . ' ' . ($s->unidad ?? '');
            if (!empty($s->nombre_cabo)) $detalle .= ' · Cabo: ' . $s->nombre_cabo;
            $eventos->push([
                'tipo'    => 'salida',
                'fecha'   => (string) $s->fecha,
                'titulo'  => 'Salida #' . $s->movimiento_id . ($s->destino ? ' — ' . $s->destino : ''),
                'detalle' => $detalle,
                'usuario' => $s->usuario,
                'delta'   => -(float) $s->cantidad,
            ]);
        }

        // Devoluciones
        foreach ($devoluciones as $d) {
            $detalle = round((float) $d->cantidad_devuelta, 2) . ' uds.';
            if (!empty($d->observaciones)) $detalle .= ' · ' . $d->observaciones;
            $eventos->push([
                'tipo'    => 'devolucion',
                'fecha'   => (string) $d->fecha,
                'titulo'  => 'Devolución · Mov. #' . $d->movimiento_id,
                'detalle' => $detalle,
                'usuario' => $d->usuario,
                'delta'   => (float) $d->cantidad_devuelta,
            ]);
        }

        // Ordenar cronológicamente y calcular saldo acumulado
        $saldo = 0.0;
        $timeline = $eventos
            ->filter(fn($e) => $e['fecha'] !== '')
            ->sortBy('fecha')
            ->values()
            ->map(function ($e) use (&$saldo) {
                $saldo += $e['delta'];
                return array_merge($e, ['saldo' => round($saldo, 2)]);
            })
            ->values();

        return response()->json([
            'insumo' => [
                'id'          => (int)  $inv->id,
                'insumo_id'   => $inv->insumo_id,
                'descripcion' => $inv->descripcion,
                'unidad'      => $inv->unidad,
            ],
            'resumen' => [
                'total_entradas'     => round($totalEntradas,     2),
                'total_salidas'      => round($totalSalidas,      2),
                'total_devoluciones' => round($totalDevoluciones, 2),
                'existencia_actual'  => round($existenciaActual,  2),
                'neto_calculado'     => round($netoCalculado,     2),
                'cuadre'             => $cuadre,
            ],
            'eventos' => $timeline,
        ]);
    }
}
