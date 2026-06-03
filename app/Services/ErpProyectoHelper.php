<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ErpProyectoHelper
{
    /**
     * Extrae el prefijo de un nombre de proyecto ERP.
     * "Aurea / Almacen"  → "Aurea /"
     * "Centro Histórico / Almacen" → "Centro Histórico /"
     */
    private static function prefijo(string $nombre): string
    {
        $pos = strpos($nombre, '/');
        return $pos !== false ? rtrim(substr($nombre, 0, $pos)) : $nombre;
    }

    /**
     * Aplica filtro ViewPUC en el query dado usando LIKE sobre el prefijo del proyecto.
     * Si hay varios proyectos, usa orWhere para cubrir todos sus prefijos.
     */
    public static function aplicarFiltroViewPUC($query, array $nombresProyecto, string $columna = 'Proyecto'): void
    {
        $prefijos = array_values(array_unique(array_map(
            fn($n) => self::prefijo($n),
            array_filter($nombresProyecto)
        )));

        if (empty($prefijos)) return;

        $query->where(function ($w) use ($prefijos, $columna) {
            foreach ($prefijos as $p) {
                $w->orWhere($columna, 'like', $p . '%');
            }
        });
    }

    /**
     * Resuelve el nombre de proyecto ERP para una sola obra local
     * y devuelve el patrón LIKE para ViewPUC.
     */
    public static function nombreParaObra(int $obraId): ?string
    {
        $erpId = DB::table('obras')->where('id', $obraId)->value('erp_proyecto_id');
        if (!$erpId) return null;

        try {
            return DB::connection('erp')
                ->table('Proyectos')
                ->where('IdProyecto', (int) $erpId)
                ->value('Proyecto');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Resuelve los nombres de proyecto ERP para un array de obra_ids locales.
     */
    public static function nombresParaObras(array $obraIds): array
    {
        $erpIds = DB::table('obras')
            ->whereIn('id', $obraIds)
            ->whereNotNull('erp_proyecto_id')
            ->pluck('erp_proyecto_id')
            ->toArray();

        if (empty($erpIds)) return [];

        try {
            return DB::connection('erp')
                ->table('Proyectos')
                ->whereIn('IdProyecto', array_map('intval', $erpIds))
                ->pluck('Proyecto')
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }
}
