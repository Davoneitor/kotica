<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SyncObrasDesdeErp
{
    public function sync(): void
    {
        $rows = DB::connection('erp')->select("
            SELECT
                UN.IdUnidadNegocio,
                UN.UnidadNegocio,
                Proy.IdProyecto,
                Proy.Proyecto
            FROM dbo.PROYECTOS Proy
            INNER JOIN dbo.AcUnidadesNegocio UN
                ON Proy.idUnidadNegocio = UN.IdUnidadNegocio
            INNER JOIN dbo.AOTipoProyectos TProy
                ON Proy.IdTipoProyecto = TProy.IdTipoProyecto
            WHERE TProy.Texto = 'Almacen'
              AND Proy.Cerrado = 0
        ");

        foreach ($rows as $r) {
            $erpProyectoId = (int) ($r->IdProyecto ?? 0);
            if ($erpProyectoId <= 0) continue;

            // ✅ si ya existe, NO hacemos nada
            $exists = DB::table('obras')->where('erp_proyecto_id', $erpProyectoId)->exists();
            if ($exists) continue;

            $nombre = $r->Proyecto ?? 'SIN NOMBRE';

            DB::table('obras')->insert([
                'nombre' => $nombre,
                'descripcion' => ($r->UnidadNegocio ?? '') . ' (' . ($r->IdUnidadNegocio ?? '') . ')',
                'erp_proyecto_id' => $erpProyectoId,
                'erp_unidad_negocio_id' => isset($r->IdUnidadNegocio) ? (int)$r->IdUnidadNegocio : null,
                'erp_unidad_negocio' => $r->UnidadNegocio ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
