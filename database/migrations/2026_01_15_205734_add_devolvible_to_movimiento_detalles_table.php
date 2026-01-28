<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
{
    Schema::table('movimiento_detalles', function (Blueprint $table) {
        $table->boolean('devolvible')->default(0)->after('cantidad');
    });
}

public function down(): void
{
    Schema::table('movimiento_detalles', function (Blueprint $table) {
        $table->dropColumn('devolvible');
    });
}

};
