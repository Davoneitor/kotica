{{-- resources/views/pdf/transferencia.blade.php --}}
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Transferencia entre obras #{{ $t->id }}</title>
    <style>
        body    { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }

        .title  { font-size: 17px; font-weight: bold; text-align: center; margin-bottom: 3px; }
        .folio  { font-size: 12px; text-align: center; color: #555; margin-bottom: 12px; }

        .meta   { width: 100%; margin-bottom: 10px; border-collapse: collapse; }
        .meta td{ padding: 7px; vertical-align: top; }
        .box    { border: 1px solid #333; }

        .tag         { display: inline-block; padding: 2px 10px; border-radius: 3px; font-size: 11px; font-weight: bold; }
        .tag-origen  { background: #e0e7ff; color: #3730a3; border: 1px solid #a5b4fc; }
        .tag-destino { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }

        .obs        { margin-top: 10px; }
        .obs .label { font-weight: bold; font-size: 12px; }
        .obs .box2  { border: 1px solid #333; padding: 8px; min-height: 28px; }

        table.items                   { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.items th, table.items td{ border: 1px solid #333; padding: 5px; vertical-align: top; }
        table.items th                { background: #f2f2f2; text-align: left; font-size: 11px; }
        table.items td                { font-size: 11px; }

        .right  { text-align: right; }
        .center { text-align: center; }

        .sign-wrap { page-break-inside: avoid; margin-top: 32px; }
        .sign-col  { display: inline-block; width: 48%; vertical-align: top; text-align: center; padding: 0 1%; }
        .signbox   { height: 88px; display: block; }
        .sigimg    { max-height: 82px; max-width: 90%; display: block; margin: 0 auto; }
        .sigline   { border-top: 1px solid #111; width: 80%; margin: 72px auto 0 auto; }
        .small     { font-size: 11px; color: #222; margin-top: 8px; text-align: center; }
    </style>
</head>
<body>

<div class="title">TRANSFERENCIA ENTRE OBRAS</div>
<div class="folio">Folio #{{ $t->id }}</div>

@php
    $observaciones = is_string($t->observaciones ?? null) ? trim($t->observaciones) : '';
@endphp

{{-- Cabecera --}}
<table class="meta box">
    <tr>
        <td style="width:50%;">
            <strong>Obra origen:</strong><br>
            <span class="tag tag-origen">{{ $t->obra_origen }}</span>
        </td>
        <td style="width:50%;">
            <strong>Obra destino:</strong><br>
            <span class="tag tag-destino">{{ $t->obra_destino }}</span>
        </td>
    </tr>
    <tr>
        <td>
            <strong>Fecha:</strong>
            {{ \Carbon\Carbon::parse($t->fecha)->format('d/m/Y') }}
        </td>
        <td>
            <strong>Encargado de almacén:</strong>
            {{ $t->usuario }}
        </td>
    </tr>
</table>

{{-- Observaciones --}}
@if(!empty($observaciones))
    <div class="obs">
        <div class="label">Observaciones:</div>
        <div class="box2">{{ $observaciones }}</div>
    </div>
@endif

{{-- Tabla de insumos --}}
<table class="items">
    <thead>
        <tr>
            <th style="width:18%">Clave</th>
            <th style="width:34%">Descripción</th>
            <th style="width:7%">Cant.</th>
            <th style="width:7%">Unid.</th>
            <th style="width:9%">Orig. antes</th>
            <th style="width:9%">Orig. desp.</th>
            <th style="width:8%">Dest. antes</th>
            <th style="width:8%">Dest. desp.</th>
        </tr>
    </thead>
    <tbody>
        @forelse($detalles as $d)
            <tr>
                <td class="right">{{ $d->insumo_id ?? '—' }}</td>
                <td>{{ $d->descripcion }}</td>
                <td class="right">
                    {{ rtrim(rtrim(number_format((float) $d->cantidad, 2, '.', ''), '0'), '.') }}
                </td>
                <td class="center">{{ $d->unidad ?? '' }}</td>
                <td class="right">{{ number_format((float) $d->origen_stock_antes,   2, '.', '') }}</td>
                <td class="right">{{ number_format((float) $d->origen_stock_despues, 2, '.', '') }}</td>
                <td class="right">{{ number_format((float) $d->destino_stock_antes,  2, '.', '') }}</td>
                <td class="right">{{ number_format((float) $d->destino_stock_despues,2, '.', '') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="center">Sin insumos registrados.</td>
            </tr>
        @endforelse
    </tbody>
</table>

{{-- Firmas --}}
<div class="sign-wrap">
    {{-- Firma izquierda: encargado de almacén --}}
    <div class="sign-col">
        <div class="signbox">
            @if($firmaLocal)
                <img class="sigimg" src="{{ $firmaLocal }}" alt="Firma del encargado">
            @else
                <div class="sigline"></div>
            @endif
        </div>
        <div class="small">
            <strong>Residente / Gerente / Director General</strong>
        </div>
    </div>

    {{-- Firma derecha: recepción en obra destino --}}
    <div class="sign-col">
        <div class="signbox">
            <div class="sigline"></div>
        </div>
        <div class="small">
            <strong>Recibido en:</strong><br>{{ $t->obra_destino }}
        </div>
    </div>
</div>

</body>
</html>
