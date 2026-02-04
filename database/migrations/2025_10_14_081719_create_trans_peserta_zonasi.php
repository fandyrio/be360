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
        Schema::create('trans_peserta_zonasi', function (Blueprint $table) {
            $table->id();
            $table->integer('id_zonasi');
            $table->integer('id_pegawai_peserta');
            $table->integer('id_pegawai_penilai');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trans_peserta_zonasi');
    }
};
