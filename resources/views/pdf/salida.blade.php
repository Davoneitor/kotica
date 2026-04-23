salida.blade

{{-- resources/views/pdf/salida.blade.php --}}
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Comprobante de salida almacén</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        .title { font-size: 16px; font-weight: bold; text-align: center; margin-bottom: 10px; }

        .meta { width: 100%; margin-bottom: 10px; border-collapse: collapse; }
        .meta td { padding: 6px; vertical-align: top; }
        .box { border: 1px solid #333; }

        .muted { color: #444; font-size: 11px; margin: 6px 0 10px; }

        table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.items th, table.items td { border: 1px solid #333; padding: 6px; vertical-align: top; }
        table.items th { background: #f2f2f2; text-align: left; }

        .right { text-align: right; }
        .center { text-align: center; }

        .sign { width: 100%; margin-top: 30px; border-collapse: collapse; }
        .sign td { padding: 10px; vertical-align: bottom; }
        .signbox { height: 170px; text-align: center; }
        .sigimg { width: 300px; height: 150px; display: block; margin: 0 auto; }
        .line { border-top: 2px solid #111; width: 85%; margin: 0 auto; margin-top: 8px; }

        .small { font-size: 11px; color: #222; margin-top: 6px; }

        .obs { margin-top: 10px; }
        .obs .label { font-weight: bold; }
        .obs .box2 { border: 1px solid #333; padding: 8px; min-height: 28px; }
    </style>
</head>
<body>

<div class="title">COMPROBANTE DE SALIDA ALMACÉN</div>

@php
    $observaciones = $movimiento->observaciones ?? null;
    $observaciones = is_string($observaciones) ? trim($observaciones) : $observaciones;

    // Firma: construir ruta local para dompdf
    $firmaPath = $movimiento->firma_recibe_path ?? null;
    $firmaPath = is_string($firmaPath) ? trim($firmaPath) : $firmaPath;

    $firmaLocal = null;
    if (!empty($firmaPath)) {
        $firmaLocal = storage_path('app/public/' . ltrim($firmaPath, '/'));
        if (!is_file($firmaLocal)) {
            $firmaLocal = null;
        }
    }
@endphp

<table class="meta box">
    <tr>
        <td style="width:50%;">
            <strong>Fecha:</strong>
            {{ \Carbon\Carbon::parse($movimiento->fecha)->format('d/m/Y h:i A') }}
        </td>
        <td style="width:50%;">
            <strong>Destino:</strong>
            {{ $destinoNombre }}
        </td>
    </tr>

    <tr>
        <td style="width:50%;">
            <strong>Encargado de almacén:</strong>
            {{ $encargado }}
        </td>
        <td style="width:50%;">
            <strong>Quién recibe:</strong>
            {{ $movimiento->nombre_cabo }}
        </td>
    </tr>
</table>

@if(!empty($observaciones))
    <div class="obs">
        <div class="label">Observaciones:</div>
        <div class="box2">{{ $observaciones }}</div>
    </div>
@endif

<table class="items">
    <thead>
        <tr>
            <th style="width: 15%;">Clave</th>
            <th style="width: 35%;">Descripción</th>
            <th style="width: 9%;">Cant.</th>
            <th style="width: 9%;">Unidad</th>
            <th style="width: 12%;">Devolvible</th>
            <th style="width: 20%;">Nivel / Depto</th>
        </tr>
    </thead>
    <tbody>
        @forelse($movimiento->detalles as $d)
            @php
                $clave = $d->insumo_id;
                if (!is_string($clave) || trim($clave) === '') {
                    $clave = $d->inventario_id;
                }

                // Build distribution text from destinos (or fall back to clasificacion)
                if ($d->destinos->count() > 0) {
                    $distLines = $d->destinos->map(function ($dest) {
                        $label = $dest->nivel ?? '';
                        if (!empty($dest->departamento)) {
                            $label .= '/' . $dest->departamento;
                        }
                        $qty = rtrim(rtrim(number_format((float)$dest->cantidad, 2, '.', ''), '0'), '.');
                        return $label . ' (' . $qty . ')';
                    })->implode("\n");
                } else {
                    $distLines = $d->clasificacion ?? '';
                    if (!empty($d->clasificacion_d)) {
                        $distLines .= '/' . $d->clasificacion_d;
                    }
                }
            @endphp

            <tr>
                <td class="right">{{ $clave }}</td>
                <td>{{ $d->descripcion }}</td>
                <td class="right">
                    {{ rtrim(rtrim(number_format((float)$d->cantidad, 2, '.', ''), '0'), '.') }}
                </td>
                <td class="center">{{ $d->unidad }}</td>
                <td class="center">{{ ((int)($d->devolvible ?? 0) === 1) ? 'Sí' : 'No' }}</td>
                <td style="white-space:pre-line;font-size:10px;">{{ $distLines }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="center">Sin materiales.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="sign">
    <tr>
        <td style="width: 50%;">
            <table style="width:100%;height:170px;border-collapse:collapse;">
                <tr>
                    <td style="vertical-align:bottom;text-align:center;padding-bottom:0;">
                        @if($firmaLocal)
                            <img class="sigimg" src="{{ $firmaLocal }}" alt="Firma de quien recibe">
                        @else
                            <div class="line"></div>
                        @endif
                    </td>
                </tr>
            </table>
            <div class="small">
                <strong>Firma de quien recibe:</strong> {{ $movimiento->nombre_cabo }}
            </div>
        </td>

        <td style="width: 50%;">
            <table style="width:100%;height:170px;border-collapse:collapse;">
                <tr>
                    <td style="vertical-align:bottom;text-align:center;padding-bottom:0;">
                        <div class="line"></div>
                    </td>
                </tr>
            </table>
            <div class="small">
                <strong>Firma encargado de almacén:</strong> {{ $encargado }}
            </div>
        </td>
    </tr>
</table>

</body>
</html>
