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

        .sign { width: 100%; margin-top: 25px; border-collapse: collapse; }
        .sign td { padding: 18px 6px; vertical-align: top; }

        /* Ajustes para firma */
        .signbox { margin-top: 10px; height: 80px; }
        .sigimg { height: 80px; width: auto; display: block; }
        .line { border-top: 1px solid #111; width: 90%; height: 1px; margin-top: 10px; }

        .small { font-size: 11px; color: #222; margin-top: 6px; }

        .obs { margin-top: 10px; }
        .obs .label { font-weight: bold; }
        .obs .box2 { border: 1px solid #333; padding: 8px; min-height: 28px; }
    </style>
</head>
<body>

<div class="title">COMPROBANTE DE SALIDA ALMACÉN</div>

@php
    $nivelHeader = optional($movimiento->detalles->first())->clasificacion ?? '';
    $deptoHeader = optional($movimiento->detalles->first())->clasificacion_d ?? '';

    $esSotano = is_string($nivelHeader) && strtoupper(substr($nivelHeader, 0, 1)) === 'S';
    if ($esSotano) {
        $deptoHeader = '';
    }

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
            {{ \Carbon\Carbon::parse($movimiento->fecha)->format('d/m/Y H:i') }}
        </td>
        <td style="width:50%;">
            <strong>Destino:</strong>
            {{ $movimiento->destino }}
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

    <tr>
        <td style="width:50%;">
            <strong>Nivel:</strong>
            {{ $nivelHeader }}
        </td>
        <td style="width:50%;">
            <strong>Departamento:</strong>
            {{ $deptoHeader }}
        </td>
    </tr>
</table>

<div class="muted">
    * Nota: Si el nivel es S1–S5 (sótanos), el departamento puede ir vacío.
</div>

@if(!empty($observaciones))
    <div class="obs">
        <div class="label">Observaciones:</div>
        <div class="box2">{{ $observaciones }}</div>
    </div>
@endif

<table class="items">
    <thead>
        <tr>
            <th style="width: 18%;">Clave</th>
            <th style="width: 47%;">Descripción</th>
            <th style="width: 10%;">Cant.</th>
            <th style="width: 10%;">Unidad</th>
            <th style="width: 15%;">Devolvible</th>
        </tr>
    </thead>
    <tbody>
        @forelse($movimiento->detalles as $d)
            @php
                $clave = $d->insumo_id;
                if (!is_string($clave) || trim($clave) === '') {
                    $clave = $d->inventario_id;
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
            </tr>
        @empty
            <tr>
                <td colspan="5" class="center">Sin materiales.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="sign">
    <tr>
        <td style="width: 50%;">
            <div class="signbox">
                @if($firmaLocal)
                    <img class="sigimg" src="{{ $firmaLocal }}" alt="Firma de quien recibe">
                @else
                    <div class="line"></div>
                @endif
            </div>

            <div class="small">
                <strong>Firma de quien recibe:</strong> {{ $movimiento->nombre_cabo }}
            </div>
        </td>

        <td style="width: 50%;">
            <div class="signbox">
                {{-- sin raya --}}
            </div>

            <div class="small">
                <strong>Firma encargado de almacén:</strong> {{ $encargado }}
            </div>
        </td>
    </tr>
</table>

</body>
</html>
