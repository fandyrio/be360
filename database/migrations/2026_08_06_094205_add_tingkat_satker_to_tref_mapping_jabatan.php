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
        Schema::table('tref_mapping_jabatan', function (Blueprint $table) {
            $table->integer("mapping_tingkat_satker")->after("id");
            $table->integer("tingkat_satker_peserta")->after("id_jabatan_peserta");
            $table->integer("tingkat_satker_penilai")->after("id_jabatan_penilai");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tref_mapping_jabatan', function (Blueprint $table) {
            //
        });
    }
};
