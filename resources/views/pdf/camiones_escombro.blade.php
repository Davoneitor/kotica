<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Control de Camiones</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; margin: 0; padding: 14px; }
        h1  { font-size: 13px; font-weight: bold; text-align: center; margin: 0 0 4px; }
        .sub { text-align: center; font-size: 9px; color: #555; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 4px 5px; vertical-align: middle; }
        th { background: #f2f2f2; font-weight: bold; text-align: center; font-size: 8px; text-transform: uppercase; }
        .right { text-align: right; }
        .center { text-align: center; }
        .day-header td {
            background: #1f2937;
            color: #ffffff;
            font-weight: bold;
            font-size: 10px;
            padding: 5px 6px;
            border-color: #374151;
        }
        .day-total td {
            background: #e8e8e8;
            font-weight: bold;
            font-size: 9px;
        }
        .total-row td { font-weight: bold; background: #111827; color: #fff; }
        .footer { margin-top: 12px; font-size: 8px; color: #666; text-align: right; }
    </style>
</head>
<body>

<h1>CONTROL SALIDA CAMIONES</h1>
<div class="sub">
    Obra: {{ $obra?->nombre ?? 'N/D' }} &nbsp;|&nbsp;
    Período: {{ $desde ? \Carbon\Carbon::parse($desde)->format('d/m/Y') : 'Inicio' }} – {{ $hasta ? \Carbon\Carbon::parse($hasta)->format('d/m/Y') : 'Hoy' }}
</div>

<table>
    <thead>
        <tr>
            <th>H. Entrada</th>
            <th>H. Salida</th>
            <th>Tipo material</th>
            <th>Placas</th>
            <th>m³</th>
            <th>Cód. recibo</th>
            <th>Usuario</th>
        </tr>
    </thead>
    <tbody>
        @forelse($grupos as $grupo)
            {{-- Encabezado del día --}}
            <tr class="day-header">
                <td colspan="4">{{ $grupo['fecha'] }}</td>
                <td class="right">{{ $grupo['total_dia'] }} m³</td>
                <td colspan="2"></td>
            </tr>

            {{-- Registros del día --}}
            @foreach($grupo['filas'] as $r)
            <tr>
                <td class="center">{{ $r['hora_entrada'] }}</td>
                <td class="center">{{ $r['hora_salida'] }}</td>
                <td>{{ $r['tipo_material'] }}</td>
                <td class="center">{{ $r['placas'] }}</td>
                <td class="right">{{ $r['metros_cubicos'] }}</td>
                <td class="center">{{ $r['folio_recibo'] }}</td>
                <td>{{ $r['usuario'] }}</td>
            </tr>
            @endforeach

        @empty
            <tr>
                <td colspan="7" class="center">Sin registros en el período seleccionado.</td>
            </tr>
        @endforelse

        @if(count($grupos) > 0)
        <tr class="total-row">
            <td colspan="4" class="right">TOTAL PERÍODO</td>
            <td class="right">{{ $totalM3 }} m³</td>
            <td colspan="2"></td>
        </tr>
        @endif
    </tbody>
</table>

<div class="footer">
    Generado: {{ $fecha_generacion }} &nbsp;|&nbsp; Sistema Kotica
</div>

</body>
</html>
