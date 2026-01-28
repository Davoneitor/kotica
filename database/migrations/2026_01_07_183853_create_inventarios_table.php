<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventarios', function (Blueprint $table) {
            $table->id(); // BIGINT PK AI

            $table->string('familia', 80);
            $table->string('subfamilia', 80)->nullable();
            $table->string('descripcion', 150);
            $table->string('unidad', 50);

            $table->foreignId('obra_id')->constrained('obras')->cascadeOnDelete();

            // ✅ Por ahora proveedor como TEXTO (no FK)
            $table->string('proveedor', 150);

            $table->decimal('cantidad', 12, 2)->default(0);
            $table->decimal('cantidad_teorica', 12, 2)->default(0);
            $table->decimal('en_espera', 12, 2)->default(0);
            $table->decimal('costo_promedio', 12, 2)->default(0);

            $table->string('destino', 100);

            $table->timestamps();

            $table->index('obra_id');
            $table->index(['familia', 'subfamilia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventarios');
    }
};
