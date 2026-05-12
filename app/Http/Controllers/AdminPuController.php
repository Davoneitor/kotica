<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminPuController extends Controller
{
    private const ADMIN_EMAIL = 'david.berumen.lozano@gmail.com';
    private const ADMIN_ID    = 14;

    private function authorize(): void
    {
        $user = Auth::user();
        if (! $user || ($user->id !== self::ADMIN_ID && $user->email !== self::ADMIN_EMAIL)) {
            abort(403, 'Acceso no autorizado.');
        }
    }

    public function index()
    {
        $this->authorize();
        return view('admin.actualizar-pu');
    }

    public function stats()
    {
        $this->authorize();

        return response()->json([
            'entradas' => $this->statsEntradas(),
            'salidas'  => $this->statsSalidas(),
            'enviadas' => $this->statsEnviadas(),
            'recibidas'=> $this->statsRecibidas(),
        ]);
    }

    public function run(Request $request)
    {
        $this->authorize();

        $tipo    = $request->input('tipo', 'todos');
        $forzado = $request->boolean('forzado', false);

        $t0      = microtime(true);
        $results = [];

        if ($tipo === 'todos' || $tipo === 'entradas') {
            $results['entradas'] = $this->actualizarEntradas($forzado);
        }
        if ($tipo === 'todos' || $tipo === 'salidas') {
            $results['salidas'] = $this->actualizarSalidas($forzado);
        }
        if ($tipo === 'todos' || $tipo === 'enviadas') {
            $results['enviadas'] = $this->actualizarEnviadas($forzado);
        }
        if ($tipo === 'todos' || $tipo === 'recibidas') {
            $results['recibidas'] = $this->actualizarRecibidas($forzado);
        }

        $results['tiempo_ms'] = round((microtime(true) - $t0) * 1000);

        return response()->json($results);
    }

    // ─── Stats ────────────────────────────────────────────────────────────────

    private function statsEntradas(): array
    {
        $base   = DB::table('oc_recepciones')->whereIn('tipo', ['oc', 'manual']);
        $total  = (clone $base)->count();
        $conPu  = (clone $base)->where('precio_unitario', '>', 0)->count();
        $sinPu  = (clone $base)->whereNull('precio_unitario')->count();
        $puCero = (clone $base)->where('precio_unitario', 0)->count();

        $actualizables = (int)(DB::selectOne("
            SELECT COUNT(*) AS cnt
            FROM oc_recepciones ocr
            INNER JOIN inventarios inv
                ON inv.insumo_id = ocr.insumo AND inv.obra_id = ocr.obra_id
            WHERE ocr.tipo IN ('oc', 'manual')
              AND inv.costo_promedio > 0
              AND (ocr.precio_unitario IS NULL OR ocr.precio_unitario = 0)
        ")->cnt ?? 0);

        $sinCoincidencia = (int)(DB::selectOne("
            SELECT COUNT(*) AS cnt
            FROM oc_recepciones ocr
            LEFT JOIN inventarios inv
                ON inv.insumo_id = ocr.insumo AND inv.obra_id = ocr.obra_id
                AND inv.costo_promedio > 0
            WHERE ocr.tipo IN ('oc', 'manual')
              AND (ocr.precio_unitario IS NULL OR ocr.precio_unitario = 0)
              AND inv.id IS NULL
        ")->cnt ?? 0);

        return compact('total', 'conPu', 'sinPu', 'puCero', 'actualizables', 'sinCoincidencia');
    }

    private function statsSalidas(): array
    {
        $total    = DB::table('movimiento_detalles')->count();
        $conPu    = DB::table('movimiento_detalles')->where('precio_unitario', '>', 0)->count();
        $sinPu    = DB::table('movimiento_detalles')->whereNull('precio_unitario')->count();
        $puCero   = DB::table('movimiento_detalles')->where('precio_unitario', 0)->count();

        $actualizables = (int)(DB::selectOne("
            SELECT COUNT(*) AS cnt
            FROM movimiento_detalles md
            INNER JOIN inventarios inv ON inv.id = md.inventario_id
            WHERE inv.costo_promedio > 0
              AND (md.precio_unitario IS NULL OR md.precio_unitario = 0)
        ")->cnt ?? 0);

        $sinCoincidencia = (int)(DB::selectOne("
            SELECT COUNT(*) AS cnt
            FROM movimiento_detalles md
            LEFT JOIN inventarios inv
                ON inv.id = md.inventario_id AND inv.costo_promedio > 0
            WHERE (md.precio_unitario IS NULL OR md.precio_unitario = 0)
              AND inv.id IS NULL
        ")->cnt ?? 0);

        return compact('total', 'conPu', 'sinPu', 'puCero', 'actualizables', 'sinCoincidencia');
    }

    private function statsEnviadas(): array
    {
        $total  = DB::table('transferencias_entre_obras_detalle')->count();
        $conPu  = DB::table('transferencias_entre_obras_detalle')->where('precio_unitario', '>', 0)->count();
        $sinPu  = DB::table('transferencias_entre_obras_detalle')->whereNull('precio_unitario')->count();
        $puCero = DB::table('transferencias_entre_obras_detalle')->where('precio_unitario', 0)->count();

        // Actualizables = por insumo_id O por descripción
        $actualizables = (int)(DB::selectOne("
            SELECT COUNT(*) AS cnt
            FROM transferencias_entre_obras_detalle ted
            WHERE (ted.precio_unitario IS NULL OR ted.precio_unitario = 0)
              AND (
                EXISTS (
                    SELECT 1 FROM transferencias_entre_obras te
                    INNER JOIN inventarios inv ON inv.insumo_id = ted.insumo_id AND inv.obra_id = te.obra_origen_id
                    WHERE te.id = ted.transferencia_id AND inv.costo_promedio > 0
                )
                OR EXISTS (
                    SELECT 1 FROM inventarios inv
                    WHERE LTRIM(RTRIM(LOWER(inv.descripcion))) = LTRIM(RTRIM(LOWER(ted.descripcion)))
                      AND inv.costo_promedio > 0
                )
              )
        ")->cnt ?? 0);

        $sinCoincidencia = (int)(DB::selectOne("
            SELECT COUNT(*) AS cnt
            FROM transferencias_entre_obras_detalle ted
            WHERE (ted.precio_unitario IS NULL OR ted.precio_unitario = 0)
              AND NOT EXISTS (
                SELECT 1 FROM inventarios inv
                WHERE LTRIM(RTRIM(LOWER(inv.descripcion))) = LTRIM(RTRIM(LOWER(ted.descripcion)))
                  AND inv.costo_promedio > 0
              )
        ")->cnt ?? 0);

        return compact('total', 'conPu', 'sinPu', 'puCero', 'actualizables', 'sinCoincidencia');
    }

    private function statsRecibidas(): array
    {
        $total  = DB::table('oc_recepciones')->where('tipo', 'transferencia')->count();
        $conPu  = DB::table('oc_recepciones')->where('tipo', 'transferencia')->where('precio_unitario', '>', 0)->count();
        $sinPu  = DB::table('oc_recepciones')->where('tipo', 'transferencia')->whereNull('precio_unitario')->count();
        $puCero = DB::table('oc_recepciones')->where('tipo', 'transferencia')->where('precio_unitario', 0)->count();

        $actualizables = (int)(DB::selectOne("
            SELECT COUNT(*) AS cnt
            FROM oc_recepciones ocr
            WHERE ocr.tipo = 'transferencia'
              AND (ocr.precio_unitario IS NULL OR ocr.precio_unitario = 0)
              AND (
                EXISTS (
                    SELECT 1 FROM inventarios inv
                    WHERE inv.insumo_id = ocr.insumo AND inv.obra_id = ocr.obra_id
                      AND inv.costo_promedio > 0
                )
                OR EXISTS (
                    SELECT 1 FROM inventarios inv
                    WHERE LTRIM(RTRIM(LOWER(inv.descripcion))) = LTRIM(RTRIM(LOWER(ocr.descripcion)))
                      AND inv.costo_promedio > 0
                )
              )
        ")->cnt ?? 0);

        $sinCoincidencia = (int)(DB::selectOne("
            SELECT COUNT(*) AS cnt
            FROM oc_recepciones ocr
            WHERE ocr.tipo = 'transferencia'
              AND (ocr.precio_unitario IS NULL OR ocr.precio_unitario = 0)
              AND NOT EXISTS (
                SELECT 1 FROM inventarios inv
                WHERE LTRIM(RTRIM(LOWER(inv.descripcion))) = LTRIM(RTRIM(LOWER(ocr.descripcion)))
                  AND inv.costo_promedio > 0
              )
        ")->cnt ?? 0);

        return compact('total', 'conPu', 'sinPu', 'puCero', 'actualizables', 'sinCoincidencia');
    }

    // ─── Updates (SQL Server FROM…JOIN syntax) ────────────────────────────────

    private function actualizarEntradas(bool $forzado): array
    {
        $whereVacios = "ocr.tipo IN ('oc', 'manual') AND inv.costo_promedio > 0 AND (ocr.precio_unitario IS NULL OR ocr.precio_unitario = 0)";
        $whereTodos  = "ocr.tipo IN ('oc', 'manual') AND inv.costo_promedio > 0";

        $sinCoincidencia = (int)(DB::selectOne("
            SELECT COUNT(*) AS cnt
            FROM oc_recepciones ocr
            LEFT JOIN inventarios inv
                ON inv.insumo_id = ocr.insumo AND inv.obra_id = ocr.obra_id
                AND inv.costo_promedio > 0
            WHERE ocr.tipo IN ('oc', 'manual')
              AND (ocr.precio_unitario IS NULL OR ocr.precio_unitario = 0)
              AND inv.id IS NULL
        ")->cnt ?? 0);

        $actualizados = DB::update("
            UPDATE ocr
            SET ocr.precio_unitario = inv.costo_promedio,
                ocr.updated_at = GETDATE()
            FROM oc_recepciones AS ocr
            INNER JOIN inventarios AS inv
                ON inv.insumo_id = ocr.insumo AND inv.obra_id = ocr.obra_id
            WHERE " . ($forzado ? $whereTodos : $whereVacios));

        return [
            'actualizados'    => $actualizados,
            'sin_coincidencia'=> $sinCoincidencia,
        ];
    }

    private function actualizarSalidas(bool $forzado): array
    {
        $whereVacios = "inv.costo_promedio > 0 AND (md.precio_unitario IS NULL OR md.precio_unitario = 0)";
        $whereTodos  = "inv.costo_promedio > 0";

        $sinCoincidencia = (int)(DB::selectOne("
            SELECT COUNT(*) AS cnt
            FROM movimiento_detalles md
            LEFT JOIN inventarios inv ON inv.id = md.inventario_id AND inv.costo_promedio > 0
            WHERE (md.precio_unitario IS NULL OR md.precio_unitario = 0)
              AND inv.id IS NULL
        ")->cnt ?? 0);

        $actualizados = DB::update("
            UPDATE md
            SET md.precio_unitario = inv.costo_promedio,
                md.updated_at = GETDATE()
            FROM movimiento_detalles AS md
            INNER JOIN inventarios AS inv ON inv.id = md.inventario_id
            WHERE " . ($forzado ? $whereTodos : $whereVacios));

        return [
            'actualizados'    => $actualizados,
            'sin_coincidencia'=> $sinCoincidencia,
        ];
    }

    private function actualizarEnviadas(bool $forzado): array
    {
        $condVacios = "(ted.precio_unitario IS NULL OR ted.precio_unitario = 0)";
        $condPU     = "inv.costo_promedio > 0";

        // Paso 1: match por insumo_id + obra_origen
        $n1 = DB::update("
            UPDATE ted
            SET ted.precio_unitario = inv.costo_promedio,
                ted.updated_at = GETDATE()
            FROM transferencias_entre_obras_detalle AS ted
            INNER JOIN transferencias_entre_obras AS te ON te.id = ted.transferencia_id
            INNER JOIN inventarios AS inv
                ON inv.insumo_id = ted.insumo_id AND inv.obra_id = te.obra_origen_id
            WHERE {$condPU}
              AND " . ($forzado ? '1=1' : $condVacios));

        // Paso 2: fallback por descripción (insumo_ids distintos entre sistemas)
        $n2 = DB::update("
            UPDATE ted
            SET ted.precio_unitario = (
                SELECT TOP 1 inv.costo_promedio
                FROM inventarios inv
                WHERE LTRIM(RTRIM(LOWER(inv.descripcion))) = LTRIM(RTRIM(LOWER(ted.descripcion)))
                  AND inv.costo_promedio > 0
                ORDER BY inv.updated_at DESC
            ),
            ted.updated_at = GETDATE()
            FROM transferencias_entre_obras_detalle AS ted
            WHERE {$condVacios}
              AND EXISTS (
                SELECT 1 FROM inventarios inv
                WHERE LTRIM(RTRIM(LOWER(inv.descripcion))) = LTRIM(RTRIM(LOWER(ted.descripcion)))
                  AND inv.costo_promedio > 0
              )");

        $sinCoincidencia = (int)(DB::selectOne("
            SELECT COUNT(*) AS cnt
            FROM transferencias_entre_obras_detalle ted
            WHERE (ted.precio_unitario IS NULL OR ted.precio_unitario = 0)
              AND NOT EXISTS (
                SELECT 1 FROM inventarios inv
                WHERE LTRIM(RTRIM(LOWER(inv.descripcion))) = LTRIM(RTRIM(LOWER(ted.descripcion)))
                  AND inv.costo_promedio > 0
              )
        ")->cnt ?? 0);

        return [
            'actualizados'    => $n1 + $n2,
            'sin_coincidencia'=> $sinCoincidencia,
        ];
    }

    private function actualizarRecibidas(bool $forzado): array
    {
        $condVacios = "(ocr.precio_unitario IS NULL OR ocr.precio_unitario = 0)";
        $condPU     = "inv.costo_promedio > 0";

        // Paso 1: match por insumo_id + obra
        $n1 = DB::update("
            UPDATE ocr
            SET ocr.precio_unitario = inv.costo_promedio,
                ocr.updated_at = GETDATE()
            FROM oc_recepciones AS ocr
            INNER JOIN inventarios AS inv
                ON inv.insumo_id = ocr.insumo AND inv.obra_id = ocr.obra_id
            WHERE ocr.tipo = 'transferencia'
              AND {$condPU}
              AND " . ($forzado ? '1=1' : $condVacios));

        // Paso 2: fallback por descripción
        $n2 = DB::update("
            UPDATE ocr
            SET ocr.precio_unitario = (
                SELECT TOP 1 inv.costo_promedio
                FROM inventarios inv
                WHERE LTRIM(RTRIM(LOWER(inv.descripcion))) = LTRIM(RTRIM(LOWER(ocr.descripcion)))
                  AND inv.costo_promedio > 0
                ORDER BY inv.updated_at DESC
            ),
            ocr.updated_at = GETDATE()
            FROM oc_recepciones AS ocr
            WHERE ocr.tipo = 'transferencia'
              AND {$condVacios}
              AND EXISTS (
                SELECT 1 FROM inventarios inv
                WHERE LTRIM(RTRIM(LOWER(inv.descripcion))) = LTRIM(RTRIM(LOWER(ocr.descripcion)))
                  AND inv.costo_promedio > 0
              )");

        $sinCoincidencia = (int)(DB::selectOne("
            SELECT COUNT(*) AS cnt
            FROM oc_recepciones ocr
            WHERE ocr.tipo = 'transferencia'
              AND (ocr.precio_unitario IS NULL OR ocr.precio_unitario = 0)
              AND NOT EXISTS (
                SELECT 1 FROM inventarios inv
                WHERE LTRIM(RTRIM(LOWER(inv.descripcion))) = LTRIM(RTRIM(LOWER(ocr.descripcion)))
                  AND inv.costo_promedio > 0
              )
        ")->cnt ?? 0);

        return [
            'actualizados'    => $n1 + $n2,
            'sin_coincidencia'=> $sinCoincidencia,
        ];
    }
}
