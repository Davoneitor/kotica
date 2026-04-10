<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventarios', function (Blueprint $table) {
            if (! Schema::hasColumn('inventarios', 'obsoleto')) {
                $table->boolean('obsoleto')->default(false)->after('devolvible');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventarios', function (Blueprint $table) {
            if (Schema::hasColumn('inventarios', 'obsoleto')) {
                $table->dropColumn('obsoleto');
            }
        });
    }
};
