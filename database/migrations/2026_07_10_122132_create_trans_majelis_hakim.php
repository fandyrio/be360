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
        Schema::create('trans_majelis_hakim', function (Blueprint $table) {
            $table->id();
            $table->integer("IdObservee_hakim_peserta");
            $table->integer("IdObservee_hakim_penilai");
            $table->integer("id_periode");
            $table->integer("id_zonasi_satker");
            $table->boolean("status")->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trans_majelis_hakim');
    }
};
