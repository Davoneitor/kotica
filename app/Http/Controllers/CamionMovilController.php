<?php

namespace App\Http\Controllers;

use App\Models\SalidaCamionEscombro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CamionMovilController extends Controller
{
    /**
     * GET /api/camiones/hoy
     * Registros de hoy + total m³ para la obra actual.
     */
    public function hoy(Request $request)
    {
        $user = Auth::user();
        $obraId = (int) ($user?->obra_actual_id ?? 0);
        if (!$obraId) return response()->json(['total' => 0, 'registros' => []]);

        $fecha = $request->get('fecha', now()->toDateString());

        $registros = SalidaCamionEscombro::where('obra_id', $obraId)
            ->whereDate('fecha', $fecha)
            ->orderBy('id', 'desc')
            ->get(['id', 'hora_entrada', 'hora_salida', 'tipo_material', 'placas', 'metros_cubicos', 'folio_recibo', 'foto_vale', 'foto_camion', 'created_at']);

        $total = $registros->sum('metros_cubicos');

        return response()->json([
            'total'     => (float) $total,
            'registros' => $registros->values(),
        ]);
    }

    /**
     * GET /api/camiones/placas
     * Placas usadas anteriormente (autocomplete).
     */
    public function placas()
    {
        $user = Auth::user();
        $obraId = (int) ($user?->obra_actual_id ?? 0);
        if (!$obraId) return response()->json([]);

        $placas = SalidaCamionEscombro::where('obra_id', $obraId)
            ->whereNotNull('placas')
            ->where('placas', '!=', '')
            ->distinct()
            ->orderBy('placas')
            ->pluck('placas');

        return response()->json($placas);
    }

    /**
     * POST /api/camiones
     * Guarda un nuevo registro. Fotos llegan como base64 en JSON.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $obraId = (int) ($user?->obra_actual_id ?? 0);
        if (!$obraId) {
            return response()->json(['ok' => false, 'message' => 'Sin obra asignada.'], 422);
        }

        $request->validate([
            'fecha'          => ['required', 'date'],
            'hora_entrada'   => ['required', 'string', 'max:10'],
            'hora_salida'    => ['required', 'string', 'max:10'],
            'tipo_material'  => ['required', 'string', 'max:100'],
            'placas'         => ['required', 'string', 'max:30'],
            'metros_cubicos' => ['required', 'numeric', 'min:0.01'],
            'folio_recibo'   => ['required', 'string', 'max:100'],
            'foto_vale'      => ['required', 'string'],
            'foto_camion'    => ['required', 'string'],
        ]);

        $fotoValePath   = $this->guardarFotoBase64($request->foto_vale,   "escombro/obra_{$obraId}");
        $fotoCamionPath = $this->guardarFotoBase64($request->foto_camion, "escombro/obra_{$obraId}");

        if (!$fotoValePath || !$fotoCamionPath) {
            return response()->json(['ok' => false, 'message' => 'Error al guardar las fotos.'], 422);
        }

        $registro = SalidaCamionEscombro::create([
            'obra_id'        => $obraId,
            'user_id'        => Auth::id(),
            'fecha'          => $request->fecha,
            'hora_entrada'   => $request->hora_entrada,
            'hora_salida'    => $request->hora_salida,
            'tipo_material'  => $request->tipo_material,
            'placas'         => strtoupper(trim($request->placas)),
            'metros_cubicos' => (float) $request->metros_cubicos,
            'folio_recibo'   => $request->folio_recibo,
            'foto_vale'      => $fotoValePath,
            'foto_camion'    => $fotoCamionPath,
        ]);

        $total = (float) SalidaCamionEscombro::where('obra_id', $obraId)
            ->whereDate('fecha', $request->fecha)
            ->sum('metros_cubicos');

        return response()->json([
            'ok'        => true,
            'id'        => $registro->id,
            'total_dia' => $total,
            'registro'  => $registro->only(['id', 'hora_entrada', 'hora_salida', 'tipo_material', 'placas', 'metros_cubicos', 'folio_recibo', 'foto_vale', 'foto_camion', 'created_at']),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function guardarFotoBase64(string $dataUrl, string $dir): ?string
    {
        if (!preg_match('/^data:image\/(png|jpeg|jpg|webp);base64,/', $dataUrl)) {
            return null;
        }
        $base64 = substr($dataUrl, strpos($dataUrl, ',') + 1);
        $binary = base64_decode($base64, true);
        if ($binary === false || strlen($binary) > 10 * 1024 * 1024) return null;

        $ext      = str_contains($dataUrl, 'image/png') ? 'png' : 'jpg';
        $filename = 'cam_' . time() . '_' . substr(md5($binary), 0, 8) . '.' . $ext;
        $path     = "{$dir}/{$filename}";

        Storage::disk('public')->makeDirectory($dir);
        Storage::disk('public')->put($path, $binary);

        return Storage::disk('public')->exists($path) ? $path : null;
    }
}
