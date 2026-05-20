<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillFiniquitosFamilia extends Command
{
    protected $signature   = 'backfill:finiquitos-familia {--dry-run : Mostrar sin modificar}';
    protected $description = 'Rellena familia/subfamilia en oc_recepciones (todos los tipos) que tienen esos campos vacíos';

    public function handle(): int
    {
        $dry = $this->option('dry-run');

        // ── Lookup 1: inventarios locales (todas las obras) ──────────────
        $invLocal = DB::table('inventarios')
            ->whereNotNull('familia')
            ->where('familia', '!=', '')
            ->select('insumo_id', 'familia', 'subfamilia')
            ->get()
            ->keyBy('insumo_id');

        // ── Lookup 2: inventarios con normalización 4→5 dígitos ──────────
        // Genera un mapa: código_4_dígitos => registro
        $invNorm = [];
        foreach ($invLocal as $id => $row) {
            $short = preg_replace('/-0(\d{4})$/', '-$1', $id); // 00002 → 0002
            if ($short !== $id) {
                $invNorm[$short] = $row;
            }
        }

        // ── Lookup 3: ERP ────────────────────────────────────────────────
        $invErp    = collect();
        $invErpNorm = [];   // 4-digit fallback keys for 5-digit ERP codes
        try {
            $invErp = DB::connection('erp')
                ->table('AcCatInsumos as I')
                ->join('AcFamilias as FI', 'I.idFamilia', '=', 'FI.idFamilia')
                ->whereNotNull('FI.FamiliaPrincipal')
                ->where('FI.FamiliaPrincipal', '!=', '')
                ->select(
                    'I.Insumo as CodProducto',
                    DB::raw('FI.FamiliaPrincipal AS Familia'),
                    DB::raw('FI.Familia AS SubFamilia')
                )
                ->get()
                ->keyBy('CodProducto');

            // Build 4-digit fallback: SCEX-RET-00002 → key SCEX-RET-0002
            foreach ($invErp as $id => $row) {
                $short = preg_replace('/-0(\d{4})$/', '-$1', $id);
                if ($short !== $id) {
                    $invErpNorm[$short] = $row;
                }
            }
        } catch (\Throwable $e) {
            $this->warn('No se pudo conectar al ERP: ' . $e->getMessage());
        }

        // ── Lookup 4: ERP por prefijo de subfamilia ──────────────────────
        // Insumos como "16ON-FRR-0065" no existen en AcCatInsumos, pero su
        // prefijo "16ON-FRR" sí existe como AcFamilias.Familia → derivar familia/subfamilia.
        $invErpPrefix = [];
        if ($invErp->isNotEmpty()) {
            try {
                $erpFamilias = DB::connection('erp')
                    ->table('AcFamilias')
                    ->whereNotNull('FamiliaPrincipal')
                    ->where('FamiliaPrincipal', '!=', '')
                    ->select('Familia', 'FamiliaPrincipal')
                    ->get()
                    ->keyBy('Familia');

                foreach ($erpFamilias as $subfam => $row) {
                    $invErpPrefix[$subfam] = (object)[
                        'familia'    => $row->FamiliaPrincipal,
                        'subfamilia' => $row->Familia,
                    ];
                }
            } catch (\Throwable $e) {
                // ERP prefix lookup failed silently — already warned above
            }
        }

        $this->info("Lookup inventarios locales: {$invLocal->count()}");
        $this->info("Lookup normalizados: " . count($invNorm));
        $this->info("Lookup ERP exacto: {$invErp->count()} (normalizados: " . count($invErpNorm) . ")");
        $this->info("Lookup ERP por prefijo: " . count($invErpPrefix));

        // ── Registros a reparar (todos los tipos) ────────────────────────
        $rows = DB::table('oc_recepciones')
            ->where(function ($q) {
                $q->whereNull('familia')
                  ->orWhere('familia', '');
            })
            ->get(['id', 'insumo', 'tipo']);

        $this->info("Registros sin familia: {$rows->count()}");

        if ($rows->isEmpty()) {
            $this->info('Nada que actualizar.');
            return 0;
        }

        $actualizados = 0;
        $sinMatch     = [];

        foreach ($rows as $r) {
            $erpRow = $invErp->get($r->insumo) ?? ($invErpNorm[$r->insumo] ?? null);
            $invFromErp = $erpRow
                ? (object)['familia' => $erpRow->Familia, 'subfamilia' => $erpRow->SubFamilia]
                : null;

            // Prefix fallback: "16ON-FRR-0065" → prefix "16ON-FRR" → AcFamilias
            $prefix = (string) preg_replace('/-[^-]+$/', '', $r->insumo);
            $invFromPrefix = ($invErpPrefix[$prefix] ?? null);

            $inv = $invLocal->get($r->insumo)    // local exact
                ?? ($invNorm[$r->insumo] ?? null) // local 4-digit fallback
                ?? $invFromErp                    // ERP exact (or 4-digit)
                ?? $invFromPrefix;                // ERP por prefijo de subfamilia

            if (!$inv) {
                $sinMatch[] = $r->insumo . ' [tipo=' . ($r->tipo ?? 'oc') . ']';
                continue;
            }

            if (!$dry) {
                DB::table('oc_recepciones')
                    ->where('id', $r->id)
                    ->update([
                        'familia'    => $inv->familia    ?? '',
                        'subfamilia' => $inv->subfamilia ?? '',
                    ]);
            }
            $actualizados++;
        }

        $label = $dry ? '[DRY RUN] Se actualizarían' : 'Actualizados';
        $this->info("{$label}: {$actualizados}");

        if (!empty($sinMatch)) {
            $unique = array_unique($sinMatch);
            $this->warn('Sin coincidencia en ninguna fuente (' . count($unique) . '):');
            foreach ($unique as $c) {
                $this->line("  - {$c}");
            }
        }

        return 0;
    }
}
