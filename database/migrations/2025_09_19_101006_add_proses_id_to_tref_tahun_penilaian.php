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
        Schema::table('tref_tahun_penilaian', function (Blueprint $table) {
            $table->integer('proses_id')->after('dasar_hukum')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tref_tahun_penilaian', function (Blueprint $table) {
            //
        });
    }
};
