<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  public function up()
{
    Schema::table('movimiento_detalles', function (Blueprint $table) {
        $table->string('clasificacion', 10)->nullable()->after('devolvible');
        $table->string('clasificacion_d', 10)->nullable()->after('clasificacion');
    });
}

public function down()
{
    Schema::table('movimiento_detalles', function (Blueprint $table) {
        $table->dropColumn(['clasificacion', 'clasificacion_d']);
    });
}

};
