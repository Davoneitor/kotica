<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Comando: inventario:propagar-costo
 *
 * Propaga el costo_promedio entre registros del mismo insumo (insumo_id)
 * que existen en distintas obras.
 *
 * Lógica:
 *  1. Agrupa todos los registros de inventarios por insumo_id.
 *  2. Para cada grupo, identifica los registros con costo_promedio > 0 (fuentes).
 *  3. Si hay fuentes con valores distintos → toma la más reciente (updated_at MAX).
 *  4. Actualiza los registros del mismo insumo que tienen costo_promedio NULL o 0.
 *  5. Por defecto funciona en modo SIMULACIÓN (dry-run).
 *     Usa --ejecutar para aplicar los cambios reales.
 */
class PropagarCostoInventario extends Command
{
    protected $signature = 'inventario:propagar-costo
                            {--ejecutar : Aplica los cambios. Sin esta opción solo simula.}';

    protected $description = 'Propaga costo_promedio entre registros del mismo insumo en distintas obras.';

    public function handle(): int
    {
        $ejecutar = (bool) $this->option('ejecutar');

        $this->info('');
        $this->info('══════════════════════════════════════════════════════');
        $this->info('  Propagación de costo_promedio en inventarios');
        $this->info($ejecutar
            ? '  MODO: EJECUCIÓN REAL (dentro de transacción)'
            : '  MODO: SIMULACIÓN — no se modificará ningún dato');
        $this->info('══════════════════════════════════════════════════════');
        $this->info('');

        // ── 1. Cargar todos los registros agrupados por insumo_id ──────────────
        //
        // Traemos todos los registros en memoria para hacer la lógica en PHP.
        // Con 1,151 registros esto es completamente seguro y más portable
        // que depender de dialectos SQL específicos.
        //
        $todos = DB::table('inventarios')
            ->select('id', 'insumo_id', 'obra_id', 'descripcion', 'costo_promedio', 'updated_at')
            ->orderBy('insumo_id')
            ->orderByDesc('updated_at')
            ->get();

        // Agrupar por insumo_id
        $grupos = $todos->groupBy('insumo_id');

        // ── 2. Contadores para el resumen final ───────────────────────────────
        $totalRegistros         = $todos->count();
        $totalYaConCosto        = 0;   // registros que ya tenían costo > 0
        $totalActualizados      = 0;   // registros actualizados en este proceso
        $totalSinReferencia     = 0;   // registros sin costo y sin fuente en ninguna obra
        $totalInsumosDistintos  = $grupos->count();
        $totalInsumosSinFuente  = 0;   // insumos donde ningún registro tiene costo
        $totalInsumosConFlicto  = 0;   // insumos con múltiples costos distintos
        $totalPendientesDespues = 0;   // registros que SIGUEN sin costo tras el proceso

        // Listas para el reporte detallado
        $insumosInconsistentes = [];   // insumos con conflicto de costo
        $insumosHuerfanos      = [];   // insumos sin ninguna fuente de costo
        $detalleActualizados   = [];   // qué registros se actualizarían / actualizaron

        // ── 3. Procesar cada grupo ─────────────────────────────────────────────
        foreach ($grupos as $insumoId => $registros) {

            // Separar registros CON costo y SIN costo
            $conCosto  = $registros->filter(fn($r) => $r->costo_promedio > 0);
            $sinCosto  = $registros->filter(fn($r) => ! ($r->costo_promedio > 0));

            $totalYaConCosto += $conCosto->count();

            // Caso A: ningún registro del insumo tiene costo → no se puede propagar
            if ($conCosto->isEmpty()) {
                $totalInsumosSinFuente++;
                $totalSinReferencia += $sinCosto->count();
                $totalPendientesDespues += $sinCosto->count();

                $insumosHuerfanos[] = [
                    'insumo_id'   => $insumoId,
                    'descripcion' => $registros->first()->descripcion,
                    'registros'   => $registros->count(),
                ];
                continue;
            }

            // Caso B: existe al menos una fuente de costo
            // Verificar si hay inconsistencia (múltiples valores distintos)
            $costosDistintos = $conCosto->pluck('costo_promedio')->unique()->values();

            if ($costosDistintos->count() > 1) {
                $totalInsumosConFlicto++;

                // Tomamos el costo del registro más recientemente actualizado
                // (el primer registro ya está ordenado por updated_at DESC)
                $costoReferencia = $conCosto->first()->costo_promedio;

                $insumosInconsistentes[] = [
                    'insumo_id'        => $insumoId,
                    'descripcion'      => $registros->first()->descripcion,
                    'costos_distintos' => $costosDistintos->all(),
                    'costo_usado'      => $costoReferencia,
                    'criterio'         => 'más reciente (updated_at)',
                ];
            } else {
                // Un solo valor de costo → no hay conflicto
                $costoReferencia = $conCosto->first()->costo_promedio;
            }

            // Caso C: propagar a los registros sin costo
            if ($sinCosto->isEmpty()) {
                // Todos los registros del insumo ya tienen costo → nada que hacer
                continue;
            }

            foreach ($sinCosto as $reg) {
                $totalActualizados++;
                $detalleActualizados[] = [
                    'id'           => $reg->id,
                    'insumo_id'    => $insumoId,
                    'obra_id'      => $reg->obra_id,
                    'descripcion'  => $reg->descripcion,
                    'costo_nuevo'  => $costoReferencia,
                ];
            }
        }

        // $totalPendientesDespues ya fue acumulado en el loop (caso A).
        // Los registros que se actualizarán no cuentan como pendientes.

        // ── 4. Mostrar reporte de inconsistencias ─────────────────────────────
        if (! empty($insumosInconsistentes)) {
            $this->warn('');
            $this->warn('  ⚠  INSUMOS CON COSTOS INCONSISTENTES (múltiples valores distintos):');
            $this->warn('  Se usará el costo del registro con updated_at más reciente.');
            $this->warn('');

            $rows = [];
            foreach ($insumosInconsistentes as $inc) {
                $rows[] = [
                    $inc['insumo_id'],
                    $inc['descripcion'],
                    implode(' / ', array_map(fn($v) => '$' . number_format($v, 2), $inc['costos_distintos'])),
                    '$' . number_format($inc['costo_usado'], 2),
                ];
            }

            $this->table(
                ['Insumo ID', 'Descripción', 'Costos distintos', 'Costo a usar'],
                $rows
            );
        }

        // ── 5. Mostrar insumos huérfanos (sin ninguna fuente de costo) ─────────
        if (! empty($insumosHuerfanos)) {
            $this->info('');
            $this->info('  ℹ  INSUMOS SIN REFERENCIA DE COSTO (ninguna obra tiene costo para estos):');

            $rows = [];
            foreach ($insumosHuerfanos as $h) {
                $rows[] = [$h['insumo_id'], $h['descripcion'], $h['registros']];
            }
            // Solo mostrar los primeros 20 para no saturar la pantalla
            $this->table(
                ['Insumo ID', 'Descripción', 'Registros afectados'],
                array_slice($rows, 0, 20)
            );

            if (count($rows) > 20) {
                $this->line('  ... y ' . (count($rows) - 20) . ' insumos más sin referencia.');
            }
        }

        // ── 6. Mostrar preview de registros a actualizar ──────────────────────
        if (! empty($detalleActualizados)) {
            $this->info('');
            $this->info('  Registros que se ' . ($ejecutar ? 'actualizarán' : 'actualizarían') . ':');

            $previewRows = [];
            foreach (array_slice($detalleActualizados, 0, 30) as $d) {
                $previewRows[] = [
                    $d['id'],
                    $d['insumo_id'],
                    $d['obra_id'],
                    $d['descripcion'],
                    '$' . number_format($d['costo_nuevo'], 2),
                ];
            }
            $this->table(
                ['ID', 'Insumo ID', 'Obra', 'Descripción', 'Costo a asignar'],
                $previewRows
            );

            if (count($detalleActualizados) > 30) {
                $this->line('  ... y ' . (count($detalleActualizados) - 30) . ' registros más.');
            }
        }

        // ── 7. Ejecutar actualización real (solo si --ejecutar) ───────────────
        $errores = 0;

        if ($ejecutar && ! empty($detalleActualizados)) {
            $this->info('');
            $this->info('  Aplicando cambios dentro de una transacción SQL...');

            DB::transaction(function () use ($detalleActualizados, &$totalActualizados, &$errores) {
                foreach ($detalleActualizados as $d) {
                    // Doble verificación: solo actualizar si TODAVÍA no tiene costo
                    // (por si alguien modificó la BD entre la lectura y la escritura)
                    $actualizado = DB::table('inventarios')
                        ->where('id', $d['id'])
                        ->where(function ($q) {
                            $q->whereNull('costo_promedio')
                              ->orWhere('costo_promedio', 0);
                        })
                        ->update(['costo_promedio' => $d['costo_nuevo']]);

                    if ($actualizado === 0) {
                        // El registro ya tenía costo cuando llegamos a actualizarlo
                        $errores++;
                    }
                }
            });

            $this->info('  Transacción completada correctamente.');
        }

        // ── 8. Resumen final ──────────────────────────────────────────────────
        $this->info('');
        $this->info('══════════════════════════════════════════════════════');
        $this->info('  RESUMEN ' . ($ejecutar ? 'DE EJECUCIÓN' : 'DE SIMULACIÓN'));
        $this->info('══════════════════════════════════════════════════════');

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Total registros revisados',                       $totalRegistros],
                ['Total insumos distintos (insumo_id)',             $totalInsumosDistintos],
                ['─────────────────────────────────', '─────'],
                ['Registros que ya tenían costo_promedio',          $totalYaConCosto],
                ['Registros que ' . ($ejecutar ? 'recibieron' : 'recibirían') . ' costo_promedio', $totalActualizados],
                ['─────────────────────────────────', '─────'],
                ['Registros sin fuente (no se pueden completar)',   $totalSinReferencia],
                ['Registros pendientes DESPUÉS del proceso',        $totalPendientesDespues],
                ['─────────────────────────────────', '─────'],
                ['Insumos sin ninguna referencia de costo',         $totalInsumosSinFuente],
                ['Insumos con costos inconsistentes',               $totalInsumosConFlicto],
                ['Errores al escribir (ya tenían costo al guardar)', $errores],
            ]
        );

        if (! $ejecutar) {
            $this->warn('');
            $this->warn('  Esto fue una SIMULACIÓN. Para aplicar los cambios ejecuta:');
            $this->warn('  php artisan inventario:propagar-costo --ejecutar');
            $this->warn('');
        } else {
            $this->info('');
            $this->info('  ✔ Proceso completado.');
            $this->info('');
        }

        return self::SUCCESS;
    }
}
