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
        Schema::table('tref_jabatan_peserta', function (Blueprint $table) {
            $table->integer('id_jabatan_gabungan')->after('id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tref_jabatan_peserta', function (Blueprint $table) {
            //
        });
    }
};
