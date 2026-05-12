<?php

namespace Database\Seeders;

use App\Models\Familia;
use Illuminate\Database\Seeder;

class FamiliasSeeder extends Seeder
{
    public function run(): void
    {
        $data = config('familias');

        foreach ($data as $familia => $subfamilias) {
            foreach ($subfamilias as $subfamilia) {
                Familia::firstOrCreate(
                    ['familia' => $familia, 'subfamilia' => $subfamilia]
                );
            }
        }
    }
}
