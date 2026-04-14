<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->string('uuid', 36)->nullable()->after('id');
        });
        // Filtered unique index: solo aplica cuando uuid NO es NULL
        DB::statement('CREATE UNIQUE INDEX movimientos_uuid_unique ON movimientos (uuid) WHERE uuid IS NOT NULL');
    }
    public function down(): void
    {
        DB::statement('DROP INDEX movimientos_uuid_unique ON movimientos');
        Schema::table('movimientos', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};
