<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 14px; margin: 0 0 4px 0; }
        .meta { margin: 0 0 10px 0; color: #555; font-size: 9px; }
        .meta span { margin-right: 10px; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 6px 6px; vertical-align: top; }
        thead th {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: .4px;
            color: #555;
            border-bottom: 1px solid #bbb;
            padding-top: 8px;
            padding-bottom: 8px;
        }
        tbody td {
            border-bottom: 1px solid #e6e6e6;
        }

        .col-insumo { width: 14%; font-weight: bold; }
        .col-desc { width: 42%; }
        .col-cant { width: 10%; text-align: right; }
        .col-unid { width: 8%; }
        .col-prov { width: 16%; }
        .col-fecha { width: 10%; white-space: nowrap; }

        /* ✅ Parciales en naranja suave */
        tr.parcial td { background: #ffedd5; }

        /* Zebra MUY leve */
        tr.zebra td { background: #fafafa; }
        tr.zebra.parcial td { background: #ffe7c2; }
    </style>
</head>
<body>

    <h1>Reporte de insumos pendientes y parciales</h1>
    {{-- Si prefieres el nombre genérico, cambia la línea de arriba por:
         <h1>Reporte de insumos</h1>
    --}}

    <div class="meta">
        <span>Obra: <strong>{{ $obra->nombre ?? 'Sin obra' }}</strong></span>
        <span>Filtro: <strong>{{ $q !== '' ? $q : '—' }}</strong></span>
        <span>Generado: <strong>{{ $fecha_generacion }}</strong></span>
    </div>

    <table>
        <thead>
            <tr>
                <th class="col-insumo">Insumo</th>
                <th class="col-desc">Descripción</th>
                <th class="col-cant">Piezas</th>
                <th class="col-unid">Unidad</th>
                <th class="col-prov">Proveedor</th>
                <th class="col-fecha">Fecha OC</th>
            </tr>
        </thead>

        <tbody>
        @php $i = 0; @endphp
        @forelse($rows as $r)
            @php
                $i++;
                $esParcial = ($r['estado'] ?? '') === 'parcial';
                $zebra = ($i % 2 === 0);
            @endphp

            <tr class="{{ $esParcial ? 'parcial' : '' }} {{ $zebra ? 'zebra' : '' }}">
                <td class="col-insumo">{{ $r['insumo'] }}</td>
                <td class="col-desc">{{ $r['descripcion'] }}</td>
                <td class="col-cant">{{ $r['faltante'] }}</td>
                <td class="col-unid">{{ $r['unidad'] }}</td>
                <td class="col-prov">{{ $r['razon'] }}</td>
                <td class="col-fecha">
                    {{ $r['fecha_oc'] ? \Carbon\Carbon::parse($r['fecha_oc'])->format('Y-m-d') : '—' }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" style="padding:10px; color:#666;">
                    Sin datos con los filtros actuales.
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>

</body>
</html>
