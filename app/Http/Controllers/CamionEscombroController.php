<?php

namespace App\Http\Controllers;

use App\Models\SalidaCamionEscombro;
use App\Models\Obra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\ExcelExporter;

class CamionEscombroController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $obraId = $user?->obra_actual_id;
        $obraActual = $obraId ? Obra::find($obraId) : null;

        return view('camiones_escombro.index', compact('obraActual'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $obraId = $user?->obra_actual_id;

        if (!$obraId) {
            return response()->json(['ok' => false, 'message' => 'Sin obra asignada.'], 422);
        }

        // Anti-duplicado por UUID (móvil offline)
        $uuid = $request->input('uuid');
        if ($uuid) {
            $existente = SalidaCamionEscombro::where('uuid', $uuid)->first();
            if ($existente) {
                return response()->json([
                    'ok'         => true,
                    'id'         => $existente->id,
                    'duplicado'  => true,
                    'total_dia'  => $this->calcularTotalDia((int) $obraId, $existente->fecha),
                ]);
            }
        }

        $request->validate([
            'fecha'          => ['required', 'date'],
            'hora_entrada'   => ['required', 'string', 'max:10'],
            'hora_salida'    => ['required', 'string', 'max:10'],
            'tipo_material'  => ['required', 'string', 'max:100'],
            'placas'         => ['required', 'string', 'max:30'],
            'metros_cubicos' => ['required', 'numeric', 'min:0.01'],
            'folio_recibo'   => ['required', 'string', 'max:100'],
            'foto_vale'      => ['required', 'image', 'max:8192'],
            'foto_camion'    => ['required', 'image', 'max:8192'],
        ], [
            'fecha.required'          => 'La fecha es obligatoria.',
            'hora_entrada.required'   => 'La hora de entrada es obligatoria.',
            'hora_salida.required'    => 'La hora de salida es obligatoria.',
            'tipo_material.required'  => 'El tipo de material es obligatorio.',
            'placas.required'         => 'Las placas son obligatorias.',
            'metros_cubicos.required' => 'Los metros cúbicos son obligatorios.',
            'metros_cubicos.min'      => 'Los metros cúbicos deben ser mayores a 0.',
            'folio_recibo.required'   => 'El folio del recibo es obligatorio.',
            'foto_vale.required'      => 'La foto del vale es obligatoria.',
            'foto_vale.image'         => 'El archivo del vale debe ser una imagen válida.',
            'foto_vale.max'           => 'La foto del vale no puede superar 8 MB.',
            'foto_camion.required'    => 'La foto del camión es obligatoria.',
            'foto_camion.image'       => 'El archivo del camión debe ser una imagen válida.',
            'foto_camion.max'         => 'La foto del camión no puede superar 8 MB.',
        ]);

        // Normalizar placas: mayúsculas, sin espacios/guiones/puntos
        $placasNorm = strtoupper(preg_replace('/[\s\.\-]+/', '', $request->placas));

        // Bloquear duplicado por placa en los últimos 5 minutos (mismo camión, misma obra)
        $reciente = SalidaCamionEscombro::where('obra_id', $obraId)
            ->where('fecha', $request->fecha)
            ->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(placas, ' ', ''), '-', ''), '.', '')) = ?", [$placasNorm])
            ->where('created_at', '>=', now()->subMinutes(5))
            ->first();

        if ($reciente) {
            $mins = (int) now()->diffInMinutes($reciente->created_at);
            return response()->json([
                'ok'      => false,
                'message' => "Este camión (placa {$placasNorm}) ya fue registrado hace {$mins} min. Verifica con tu compañero para evitar duplicados.",
            ], 422);
        }

        $data = [
            'uuid'           => $uuid ?: null,
            'obra_id'        => (int) $obraId,
            'user_id'        => Auth::id(),
            'fecha'          => $request->fecha,
            'hora_entrada'   => $request->hora_entrada ?: null,
            'hora_salida'    => $request->hora_salida ?: null,
            'tipo_material'  => $request->tipo_material ?: null,
            'placas'         => $placasNorm,
            'metros_cubicos' => $request->metros_cubicos ?: null,
            'folio_recibo'   => $request->folio_recibo ?: null,
        ];

        if ($request->hasFile('foto_vale')) {
            $data['foto_vale'] = $request->file('foto_vale')->store('escombro', 'public');
        }

        if ($request->hasFile('foto_camion')) {
            $data['foto_camion'] = $request->file('foto_camion')->store('escombro', 'public');
        }

        try {
            $registro = SalidaCamionEscombro::create($data);
        } catch (\Exception $e) {
            Log::error('CamionEscombro store: error al guardar', [
                'user_id' => Auth::id(),
                'obra_id' => $obraId,
                'error'   => $e->getMessage(),
            ]);
            return response()->json([
                'ok'      => false,
                'message' => 'Error al guardar el registro. Inténtalo de nuevo.',
            ], 500);
        }

        $total = $this->calcularTotalDia((int) $obraId, $request->fecha);

        return response()->json([
            'ok'        => true,
            'id'        => $registro->id,
            'total_dia' => $total,
        ]);
    }

    public function totalDia(Request $request)
    {
        $user = Auth::user();
        $obraId = (int) ($user?->obra_actual_id ?? 0);
        $fecha = $request->get('fecha', now()->toDateString());

        return response()->json([
            'total' => $this->calcularTotalDia($obraId, $fecha),
        ]);
    }

    private function calcularTotalDia(int $obraId, string $fecha): float
    {
        return (float) SalidaCamionEscombro::where('obra_id', $obraId)
            ->whereDate('fecha', $fecha)
            ->sum('metros_cubicos');
    }

    public function choferInfo(Request $request)
    {
        $placas = trim((string) $request->get('placas', ''));
        if ($placas === '') {
            return response()->json([]);
        }

        $placasNorm = strtoupper(preg_replace('/[\s\.\-]+/', '', $placas));

        $registro = SalidaCamionEscombro::whereRaw(
            "UPPER(REPLACE(REPLACE(REPLACE(placas, ' ', ''), '-', ''), '.', '')) = ?",
            [$placasNorm]
        )
        ->orderByDesc('created_at')
        ->first(['chofer', 'camion', 'tipo_material']);

        return response()->json($registro ? [
            'chofer'        => $registro->chofer        ?? '',
            'camion'        => $registro->camion         ?? '',
            'tipo_material' => $registro->tipo_material  ?? '',
        ] : []);
    }

    public function catalogos()
    {
        $user = Auth::user();
        $obraId = $user?->obra_actual_id;

        $placas = SalidaCamionEscombro::where('obra_id', $obraId)
            ->whereNotNull('placas')->where('placas', '!=', '')
            ->distinct()->orderBy('placas')->pluck('placas');

        return response()->json(compact('placas'));
    }

    public function explore(Request $request)
    {
        $user   = Auth::user();
        $obraId = $user?->obra_actual_id;
        $multi  = $user?->is_multiobra;

        $desde   = $request->get('desde');
        $hasta   = $request->get('hasta');
        $obraFiltro = $request->get('obra_id');   // filtro explícito desde el frontend

        // Filtrar siempre por la obra activa del usuario
        $obraEfectiva = $obraFiltro && $multi ? (int)$obraFiltro : $obraId;

        $rows = SalidaCamionEscombro::query()
            ->leftJoin('users',  'users.id',  '=', 'salida_camiones_escombro.user_id')
            ->leftJoin('obras',  'obras.id',  '=', 'salida_camiones_escombro.obra_id')
            ->where('salida_camiones_escombro.obra_id', $obraEfectiva)
            ->when($desde, fn($q) => $q->whereDate('salida_camiones_escombro.fecha', '>=', $desde))
            ->when($hasta, fn($q) => $q->whereDate('salida_camiones_escombro.fecha', '<=', $hasta))
            ->orderByDesc('salida_camiones_escombro.fecha')
            ->orderByDesc('salida_camiones_escombro.id')
            ->get([
                'salida_camiones_escombro.*',
                'users.name as usuario_nombre',
                'obras.nombre as obra_nombre',
            ]);

        // Calcular total por fecha en PHP para evitar diferencias de dialecto SQL
        $totalesPorFecha = [];
        foreach ($rows as $r) {
            $k = $r->fecha?->format('Y-m-d') ?? '';
            $totalesPorFecha[$k] = ($totalesPorFecha[$k] ?? 0) + ($r->metros_cubicos ?? 0);
        }

        // Detectar placas repetidas el mismo día (normalizado: sin espacios/guiones)
        $placasPorFecha = [];
        foreach ($rows as $r) {
            $dia   = $r->fecha?->format('Y-m-d') ?? '';
            $placa = strtoupper(preg_replace('/[\s\.\-]+/', '', $r->placas ?? ''));
            if ($placa === '') continue;
            $placasPorFecha[$dia][$placa] = ($placasPorFecha[$dia][$placa] ?? 0) + 1;
        }

        return response()->json($rows->map(fn($r) => [
            'id'             => $r->id,
            'fecha'          => $r->fecha?->format('d/m/Y') ?? '',
            'hora_entrada'   => $r->hora_entrada ?? '',
            'hora_salida'    => $r->hora_salida ?? '',
            'tipo_material'  => $r->tipo_material ?? '',
            'placas'         => $r->placas ?? '',
            'metros_cubicos' => $r->metros_cubicos,
            'total_dia'      => $totalesPorFecha[$r->fecha?->format('Y-m-d') ?? ''] ?? 0,
            'folio_recibo'   => $r->folio_recibo ?? '',
            'obra'           => $r->obra_nombre ?? '—',
            'usuario'        => $r->usuario_nombre ?? '—',
            'foto_vale_url'   => $r->foto_vale   ? url("/control-camiones/{$r->id}/foto/vale")   : null,
            'foto_camion_url' => $r->foto_camion ? url("/control-camiones/{$r->id}/foto/camion") : null,
            'placa_repetida' => (($placasPorFecha[$r->fecha?->format('Y-m-d') ?? ''][strtoupper(preg_replace('/[\s\.\-]+/', '', $r->placas ?? ''))] ?? 0) > 1),
        ])->values());
    }

    public function foto($id, $tipo)
    {
        $user = Auth::user();

        // Explore muestra registros de todas las obras; solo_explore no tiene obra_actual_id fija.
        // Se busca solo por id; el acceso ya está protegido por el middleware de auth.
        $registro = $user?->solo_explore
            ? SalidaCamionEscombro::findOrFail($id)
            : SalidaCamionEscombro::where('obra_id', $user?->obra_actual_id)->findOrFail($id);
        $path = $tipo === 'camion' ? $registro->foto_camion : $registro->foto_vale;

        if (!$path || !Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return Storage::disk('public')->response($path);
    }

    /**
     * Exportar Control de Salida de Camiones a Excel.
     * Aplica los mismos filtros que explore() pero sin límite de registros.
     */
    public function exportar(Request $request)
    {
        $user       = Auth::user();
        $obraId     = $user?->obra_actual_id;
        $multi      = $user?->is_multiobra;
        $obraFiltro = $request->get('obra_id');
        $obra       = $obraId ? Obra::find($obraId) : null;

        $desde = $request->get('desde');
        $hasta = $request->get('hasta');

        // Misma query que explore() — siempre filtrar por obra activa
        $obraEfectiva = $obraFiltro && $multi ? (int)$obraFiltro : $obraId;

        $rawRows = SalidaCamionEscombro::query()
            ->leftJoin('users', 'users.id', '=', 'salida_camiones_escombro.user_id')
            ->leftJoin('obras', 'obras.id', '=', 'salida_camiones_escombro.obra_id')
            ->where('salida_camiones_escombro.obra_id', $obraEfectiva)
            ->when($desde, fn($q) => $q->whereDate('salida_camiones_escombro.fecha', '>=', $desde))
            ->when($hasta, fn($q) => $q->whereDate('salida_camiones_escombro.fecha', '<=', $hasta))
            ->orderBy('salida_camiones_escombro.fecha')
            ->orderBy('salida_camiones_escombro.id')
            ->get(['salida_camiones_escombro.*', 'users.name as usuario_nombre', 'obras.nombre as obra_nombre']);

        $h24a12 = fn($t) => !$t ? '' : (function ($t) {
            [$h, $m] = explode(':', $t);
            $h = (int)$h; $ampm = $h >= 12 ? 'PM' : 'AM'; $h12 = ($h % 12) ?: 12;
            return str_pad($h12, 2, '0', STR_PAD_LEFT) . ':' . $m . ' ' . $ampm;
        })($t);

        // Agrupar por mes
        $meses = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
                  7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
        $byMes = [];
        foreach ($rawRows as $r) {
            $key = $r->fecha ? $r->fecha->format('Y-m') : '0000-00';
            $byMes[$key][] = $r;
        }
        ksort($byMes);

        $F = \PhpOffice\PhpSpreadsheet\Style\Fill::class;
        $B = \PhpOffice\PhpSpreadsheet\Style\Border::class;
        $A = \PhpOffice\PhpSpreadsheet\Style\Alignment::class;

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sh = $spreadsheet->getActiveSheet();
        $sh->setTitle('Control Camiones');
        $sh->getTabColor()->setRGB('374151');
        $sh->setShowSummaryBelow(false);

        $userName   = $user?->name ?? 'Sistema';
        $now        = now()->format('d/m/Y H:i');
        $obraNombre = $obra?->nombre ?? 'Sin obra';

        // Fila 1 — info
        $sh->setCellValue('A1', "Control Salida Camiones  |  Obra: {$obraNombre}  |  Generado: {$now}  |  Usuario: {$userName}");
        $sh->mergeCells('A1:K1');
        $sh->getStyle('A1')->applyFromArray([
            'font'      => ['bold'=>true,'size'=>9,'color'=>['rgb'=>'FFFFFF']],
            'fill'      => ['fillType'=>$F::FILL_SOLID,'startColor'=>['rgb'=>'111827']],
            'alignment' => ['horizontal'=>$A::HORIZONTAL_LEFT,'vertical'=>$A::VERTICAL_CENTER],
        ]);
        $sh->getRowDimension(1)->setRowHeight(20);

        // Fila 2 — total general
        $totalM3 = $rawRows->sum('metros_cubicos');
        $sh->setCellValue('A2', 'TOTAL GENERAL');
        $sh->mergeCells('A2:F2');
        $sh->setCellValue('G2', number_format($totalM3, 1) . ' m³');
        $sh->getStyle('A2:I2')->applyFromArray([
            'font'    => ['bold'=>true,'size'=>11,'color'=>['rgb'=>'FFFFFF']],
            'fill'    => ['fillType'=>$F::FILL_SOLID,'startColor'=>['rgb'=>'111827']],
        ]);
        $sh->getStyle('F2')->getNumberFormat()->setFormatCode('#,##0.00');
        $sh->getRowDimension(2)->setRowHeight(22);

        // Fila 3 — encabezados
        $headers = ['Fecha','H. Entrada','H. Salida','Tipo Material','Placas','m³','Total Día m³','Cód. Recibo','Usuario'];
        foreach ($headers as $i => $h) $sh->setCellValue(chr(65+$i).'3', $h);
        $sh->getStyle('A3:I3')->applyFromArray([
            'font'      => ['bold'=>true,'size'=>9,'color'=>['rgb'=>'FFFFFF']],
            'fill'      => ['fillType'=>$F::FILL_SOLID,'startColor'=>['rgb'=>'374151']],
            'alignment' => ['horizontal'=>$A::HORIZONTAL_CENTER],
        ]);
        $sh->getRowDimension(3)->setRowHeight(18);
        $sh->setAutoFilter('A3:I3');
        $sh->freezePane('A4');

        $row = 4;

        foreach ($byMes as $mesKey => $items) {
            [$anio, $mes] = explode('-', $mesKey);
            $nombreMes = ($meses[(int)$mes] ?? $mesKey) . ' ' . $anio;
            $totalMes  = array_sum(array_map(fn($r) => (float)($r->metros_cubicos ?? 0), $items));

            // Agrupar por día dentro del mes
            $byDia = [];
            foreach ($items as $r) {
                $dia = $r->fecha?->format('Y-m-d') ?? '0000-00-00';
                $byDia[$dia][] = $r;
            }
            ksort($byDia);

            // ── Fila mes ──────────────────────────────────────
            $sh->setCellValue("A{$row}", $nombreMes);
            $sh->mergeCells("A{$row}:F{$row}");
            $sh->setCellValue("G{$row}", number_format($totalMes, 1) . ' m³  — ' . count($items) . ' viajes');
            $sh->getStyle("A{$row}:I{$row}")->applyFromArray([
                'font'    => ['bold'=>true,'size'=>10,'color'=>['rgb'=>'FFFFFF']],
                'fill'    => ['fillType'=>$F::FILL_SOLID,'startColor'=>['rgb'=>'374151']],
            ]);
            $sh->getRowDimension($row)->setRowHeight(18);
            $row++;

            foreach ($byDia as $diaKey => $diaItems) {
                $totalDia   = array_sum(array_map(fn($r) => (float)($r->metros_cubicos ?? 0), $diaItems));
                $fechaLabel = \Carbon\Carbon::parse($diaKey)->format('d/m/Y');

                // ── Fila día ──────────────────────────────────
                $sh->setCellValue("A{$row}", $fechaLabel);
                $sh->mergeCells("A{$row}:F{$row}");
                $sh->setCellValue("G{$row}", number_format($totalDia, 1) . ' m³  — ' . count($diaItems) . ' viajes');
                $sh->getStyle("A{$row}:I{$row}")->applyFromArray([
                    'font'    => ['bold'=>true,'size'=>9,'color'=>['rgb'=>'1F2937']],
                    'fill'    => ['fillType'=>$F::FILL_SOLID,'startColor'=>['rgb'=>'E5E7EB']],
                    'borders' => ['top'=>['borderStyle'=>$B::BORDER_THIN,'color'=>['rgb'=>'9CA3AF']]],
                ]);
                $rd = $sh->getRowDimension($row);
                $rd->setRowHeight(16);
                $rd->setOutlineLevel(1);
                $row++;

                // ── Filas detalle del día ────────────────────
                foreach ($diaItems as $idx => $r) {
                    $bg = $idx % 2 === 0 ? 'FFFFFF' : 'F9FAFB';
                    $sh->setCellValue("A{$row}", $fechaLabel);
                    $sh->setCellValue("B{$row}", $h24a12($r->hora_entrada));
                    $sh->setCellValue("C{$row}", $h24a12($r->hora_salida));
                    $sh->setCellValue("D{$row}", (string)($r->tipo_material ?? ''));
                    $sh->setCellValue("E{$row}", (string)($r->placas ?? ''));
                    $sh->setCellValue("F{$row}", (float)($r->metros_cubicos ?? 0));
                    $sh->setCellValue("G{$row}", $totalDia);
                    $sh->setCellValue("H{$row}", (string)($r->folio_recibo ?? ''));
                    $sh->setCellValue("I{$row}", (string)($r->usuario_nombre ?? ''));
                    $sh->getStyle("F{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                    $sh->getStyle("G{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                    $sh->getStyle("A{$row}:I{$row}")->applyFromArray([
                        'font'    => ['size'=>9,'color'=>['rgb'=>'374151']],
                        'fill'    => ['fillType'=>$F::FILL_SOLID,'startColor'=>['rgb'=>$bg]],
                        'borders' => ['allBorders'=>['borderStyle'=>$B::BORDER_THIN,'color'=>['rgb'=>'E5E7EB']]],
                    ]);
                    $rd2 = $sh->getRowDimension($row);
                    $rd2->setRowHeight(15);
                    $rd2->setOutlineLevel(2);
                    $row++;
                }
            }
        }

        // Anchos de columna
        foreach (['A'=>12,'B'=>10,'C'=>10,'D'=>18,'E'=>12,'F'=>10,'G'=>16,'H'=>12,'I'=>35] as $col => $w) {
            $sh->getColumnDimension($col)->setWidth($w);
        }

        Log::info('Excel export: Control Camiones', ['user_id'=>Auth::id(),'obra_id'=>$obraId,'registros'=>$rawRows->count()]);

        $filename = 'salida_camiones_' . now()->format('Ymd_Hi') . '.xlsx';
        $writer   = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        return response()->stream(fn() => $writer->save('php://output'), 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'max-age=0, no-cache, no-store',
        ]);
    }

    public function pdf(Request $request)
    {
        $user = Auth::user();
        $obraId = $user?->obra_actual_id;
        $obraActual = $obraId ? Obra::find($obraId) : null;

        $desde = $request->get('desde') ?: null;
        $hasta = $request->get('hasta') ?: null;

        $rawRows = SalidaCamionEscombro::where('salida_camiones_escombro.obra_id', $obraId)
            ->leftJoin('users', 'users.id', '=', 'salida_camiones_escombro.user_id')
            ->when($desde, fn($q) => $q->whereDate('salida_camiones_escombro.fecha', '>=', $desde))
            ->when($hasta, fn($q) => $q->whereDate('salida_camiones_escombro.fecha', '<=', $hasta))
            ->orderBy('salida_camiones_escombro.fecha')
            ->orderBy('salida_camiones_escombro.id')
            ->get([
                'salida_camiones_escombro.*',
                'users.name as usuario_nombre',
            ]);

        // Calcular totales por fecha en PHP
        $totalesPorFecha = [];
        foreach ($rawRows as $r) {
            $k = $r->fecha?->format('Y-m-d') ?? '';
            $totalesPorFecha[$k] = ($totalesPorFecha[$k] ?? 0) + ($r->metros_cubicos ?? 0);
        }

        // Formateador de hora 24h → 12h reutilizable
        $h24 = fn($t) => !$t ? '—' : (function ($t) {
            [$h, $m] = explode(':', $t);
            $h   = (int) $h;
            $ampm = $h >= 12 ? 'PM' : 'AM';
            $h12  = ($h % 12) ?: 12;
            return str_pad($h12, 2, '0', STR_PAD_LEFT) . ':' . $m . ' ' . $ampm;
        })($t);

        // Agrupar por día para el PDF
        $grupos = $rawRows
            ->groupBy(fn($r) => $r->fecha?->format('Y-m-d') ?? '')
            ->map(function ($grupoRows, $fechaKey) use ($totalesPorFecha, $h24) {
                return [
                    'fecha'     => $grupoRows->first()->fecha?->format('d/m/Y') ?? '—',
                    'total_dia' => number_format((float) ($totalesPorFecha[$fechaKey] ?? 0), 1),
                    'filas'     => $grupoRows->map(fn($r) => [
                        'hora_entrada'   => $h24($r->hora_entrada),
                        'hora_salida'    => $h24($r->hora_salida),
                        'tipo_material'  => $r->tipo_material ?? '—',
                        'placas'         => $r->placas ?? '—',
                        'metros_cubicos' => number_format((float) ($r->metros_cubicos ?? 0), 1),
                        'folio_recibo'   => $r->folio_recibo ?? '—',
                        'usuario'        => $r->usuario_nombre ?? '—',
                    ])->values(),
                ];
            })->values();

        $totalM3 = $rawRows->sum('metros_cubicos');

        $pdf = Pdf::loadView('pdf.camiones_escombro', [
            'obra'             => $obraActual,
            'grupos'           => $grupos,
            'desde'            => $desde,
            'hasta'            => $hasta,
            'totalM3'          => number_format((float) $totalM3, 1),
            'fecha_generacion' => now()->format('d/m/Y h:i A'),
        ])->setPaper('letter', 'landscape');

        $sufijo = ($desde || $hasta)
            ? '_' . ($desde ?? 'inicio') . '_' . ($hasta ?? 'fin')
            : '_todos';

        return $pdf->download('control_camiones' . $sufijo . '.pdf');
    }
}
