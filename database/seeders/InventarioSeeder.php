<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Inventario;

class InventarioSeeder extends Seeder
{
    public function run(): void
    {
        // Familias y subfamilias (las mismas que usamos)
        $familias = [
            'Pintura' => ['Brochas','Rodillos','Vinílica','Esmalte','Sellador'],
            'Construcción' => ['Cemento','Block','Arena','Grava','Mortero'],
            'Plomería' => ['PVC','Cobre','Válvulas','Conexiones','Drenaje'],
            'Electricidad' => ['Cableado','Iluminación','Contactos','Tableros','Breakers'],
            'Herrería' => ['Perfiles','Tornillería','Soldadura','Placas','Anclas'],
        ];

        $unidades = ['Pieza','Metro','Kilo','Litro','Saco','Caja','Rollo','Cubeta'];

        $proveedores = [
            'Proveedor Central',
            'Materiales del Norte',
            'Ferretería Guadalajara',
            'Comex',
            'Cemex',
            'Eléctrica Jalisco',
        ];

        $destinos = ['ALMACEN GENERAL','NIVEL 1','NIVEL 2','SOTANO','PATIO'];

        /**
         * 👇 Aquí tomamos los IDs locales de obras,
         * pero SOLO de las obras que tienen erp_proyecto_id (las del ERP).
         */
        $obrasLocalIds = DB::table('obras')
            ->whereNotNull('erp_proyecto_id')
            ->pluck('id')
            ->values()
            ->toArray();

        if (count($obrasLocalIds) === 0) {
            $this->command->error("No hay obras con erp_proyecto_id. Primero sincroniza obras desde ERP.");
            return;
        }

        $total = 200;

        // Reparto equitativo entre obras (round robin)
        $idxObra = 0;

        for ($i = 1; $i <= $total; $i++) {
            $familia = array_rand($familias);
            $subfamilia = $familias[$familia][array_rand($familias[$familia])];

            $obraLocalId = $obrasLocalIds[$idxObra];
            $idxObra = ($idxObra + 1) % count($obrasLocalIds);

            Inventario::create([
                'familia' => $familia,
                'subfamilia' => $subfamilia,
                'descripcion' => $subfamilia . ' producto ' . $i . ' ' . Str::upper(Str::random(4)),
                'unidad' => $unidades[array_rand($unidades)],
                'obra_id' => $obraLocalId, // ✅ ID LOCAL (obras.id)
                'proveedor' => $proveedores[array_rand($proveedores)],
                'cantidad' => rand(0, 900),
                'cantidad_teorica' => rand(0, 950),
                'en_espera' => rand(0, 50),
                'costo_promedio' => rand(50, 1500),
                'destino' => $destinos[array_rand($destinos)], // ✅ no null
            ]);
        }

        $this->command->info("✅ {$total} registros insertados en inventarios.");
    }
}
 