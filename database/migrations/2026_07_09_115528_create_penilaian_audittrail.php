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
        Schema::create('penilaian_audittrail', function (Blueprint $table) {
            $table->id();
            $table->integer('id_peserta_zonasi');
            $table->integer('id_pegawai_penilai');
            $table->integer('id_pegawai_dinilai');
            $table->integer('jumlah_penilai');
            $table->integer('bobot_jabatan_penilai');
            $table->float('nilai_akhir');
            $table->float('nilai');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penilaian_audittrail');
    }
};
