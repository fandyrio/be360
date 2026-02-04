<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('trans_bobot_penilaian_periode', function (Blueprint $table) {
            $table->integer('bobot')->after('id_bobot_penilaian');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trans_bobot_penilaian_periode', function (Blueprint $table) {
            //
        });
    }
};
