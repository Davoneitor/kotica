<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Inventario500Seeder extends Seeder
{
    public function run(): void
    {
        // 1) Mapeo familia -> subfamilias (puedes cambiarlo después)
        $familias = [
            'Plomería' => ['PVC', 'Cobre', 'Conexiones', 'Válvulas', 'Tinacos', 'Bombas', 'Drenaje', 'Sanitario', 'Accesorios', 'Pegamentos'],
            'Electricidad' => ['Cableado', 'Contactos', 'Iluminación', 'Tableros', 'Breakers', 'Canalización', 'Tubería', 'Accesorios', 'Tierra física', 'Transformadores'],
            'Construcción' => ['Cemento', 'Block', 'Varilla', 'Arena', 'Grava', 'Aditivos', 'Malla', 'Yeso', 'Ladrillo', 'Mortero'],
            'Pintura' => ['Vinílica', 'Esmalte', 'Primer', 'Sellador', 'Impermeabilizante', 'Brochas', 'Rodillos', 'Cintas', 'Solventes', 'Resanadores'],
            'Herrería' => ['Perfiles', 'Placas', 'Tornillería', 'Soldadura', 'Anclas', 'Bisagras', 'Candados', 'Rejas', 'Tubulares', 'Accesorios'],
            'Carpintería' => ['Triplay', 'MDF', 'Barnices', 'Herrajes', 'Pegamentos', 'Puertas', 'Marcos', 'Closets', 'Cantos', 'Lijas'],
            'Pisos' => ['Loseta', 'Adhesivo', 'Boquilla', 'Niveladores', 'Zoclo', 'Mármol', 'Porcelanato', 'Lambrín', 'Juntas', 'Herramienta'],
            'Aluminio' => ['Ventanas', 'Canceles', 'Perfiles', 'Cristal', 'Selladores', 'Herrajes', 'Mosquiteros', 'Rieles', 'Bisagras', 'Accesorios'],
            'Seguridad' => ['EPP', 'Señalética', 'Arneses', 'Cascos', 'Guantes', 'Lentes', 'Extintores', 'Cintas', 'Botiquín', 'Conos'],
            'Limpieza' => ['Jabones', 'Desinfectantes', 'Escobas', 'Trapeadores', 'Bolsas', 'Guantes', 'Cubetas', 'Fibras', 'Cloro', 'Aromatizantes'],
        ];

        $unidades = ['Pieza','Metro','Litros','Kilos','Sacos','Toneladas','Paquete','Caja','Rollo','Cubeta'];
        $proveedores = ['Proveedor Central', 'Comex', 'Cementos del Centro', 'Eléctrica Jalisco', 'Ferretera GDL', 'Distribuidora Kotica'];

        // 2) Obtener los IDs locales de obras a partir del ERP ID
        $erpIds = [63, 64, 65, 66];

        $obras = DB::table('obras')
            ->whereIn('erp_proyecto_id', $erpIds)
            ->get(['id', 'erp_proyecto_id', 'nombre']);

        if ($obras->count() < 4) {
            $faltan = array_diff($erpIds, $obras->pluck('erp_proyecto_id')->map(fn($v) => (int)$v)->all());
            throw new \RuntimeException(
                "Faltan obras en tabla 'obras' con erp_proyecto_id: " . implode(', ', $faltan) .
                ". Primero corre el sync desde ERP."
            );
        }

        // Orden fijo: 63 Americana, 64 Centro, 65 Oblatos, 66 Moderna
        $obraMap = [];
        foreach ($obras as $o) {
            $obraMap[(int)$o->erp_proyecto_id] = (int)$o->id; // local id
        }

        $obraIdsLocales = [
            $obraMap[63], // Americana / Almacen
            $obraMap[64], // Centro Histórico / Almacen
            $obraMap[65], // Oblatos / Almacen
            $obraMap[66], // Moderna / Almacen
        ];

        // 3) Generar ~500 registros equilibrados
        $total = 500;
        $porObra = intdiv($total, 4); // 125
        $sobrantes = $total - ($porObra * 4); // por si no divide exacto

        $now = now();
        $batch = [];
        $counter = 1;

        // Para hacer descripciones variadas:
        $nombresBase = [
            'Tubo', 'Cable', 'Pintura', 'Cemento', 'Lija', 'Brocha', 'Varilla', 'Guante', 'Lampara', 'Loseta',
            'Pegamento', 'Sellador', 'Boquilla', 'Bisagra', 'Perfil', 'Extintor', 'Cloro', 'Cubeta', 'Rollo', 'Malla',
        ];

        $destinos = ['SIN DESTINO', 'Almacén', 'Nivel 1', 'Nivel 2', 'Sótano', 'Bodega', 'Área de trabajo'];

        foreach ($obraIdsLocales as $idx => $obraLocalId) {
            $cantidadEnEstaObra = $porObra + ($idx < $sobrantes ? 1 : 0);

            for ($i = 0; $i < $cantidadEnEstaObra; $i++) {
                // Elegir familia/subfamilia consistentes
                $familia = array_rand($familias);
                $sub = $familias[$familia][array_rand($familias[$familia])];

                $unidad = $unidades[array_rand($unidades)];
                $proveedor = $proveedores[array_rand($proveedores)];

                // cantidades “realistas”
                $cantidad = round(mt_rand(0, 5000) / 10, 2);          // 0.00 a 500.00
                $enEspera = round(mt_rand(0, 1000) / 10, 2);          // 0.00 a 100.00
                $teorica = max($cantidad, $cantidad + round(mt_rand(0, 1000) / 10, 2)); // >= cantidad
                $costo = round(mt_rand(50, 500000) / 100, 2);         // 0.50 a 5000.00

                $base = $nombresBase[array_rand($nombresBase)];
                $descripcion = "{$base} {$sub} " . Str::upper(Str::random(3)) . " #" . $counter;

                $batch[] = [
                    'familia' => $familia,
                    'subfamilia' => $sub,
                    'descripcion' => $descripcion,
                    'unidad' => $unidad,
                    'obra_id' => $obraLocalId,
                    'proveedor' => $proveedor,
                    'cantidad' => $cantidad,
                    'cantidad_teorica' => $teorica,
                    'en_espera' => $enEspera,
                    'costo_promedio' => $costo,
                    'destino' => $destinos[array_rand($destinos)], // NO NULL
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $counter++;

                // Insert por lotes para que no reviente memoria/tiempo
                if (count($batch) >= 500) {
                    DB::table('inventarios')->insert($batch);
                    $batch = [];
                }
            }
        }

        // Insert final
        if (!empty($batch)) {
            DB::table('inventarios')->insert($batch);
        }
    }
}
