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
        Schema::table('trans_peserta_zonasi', function (Blueprint $table) {
            $table->integer('nilai')->after('id_jabatan_plt')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trans_peserta_zonasi', function (Blueprint $table) {
            //
        });
    }
};
