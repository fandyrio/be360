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
        Schema::create('trans_nilai_peserta_zonasi', function (Blueprint $table) {
            $table->id();
            $table->integer('id_peserta_zonasi');
            $table->integer('id_pertanyaan');
            $table->integer('nilai');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trans_nilai_peserta_zonasi');
    }
};
