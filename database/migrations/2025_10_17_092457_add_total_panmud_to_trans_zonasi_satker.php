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
        Schema::table('trans_zonasi_satker', function (Blueprint $table) {
            $table->integer('total_panmud')->after('jumlah_personil');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trans_zonasi_satker', function (Blueprint $table) {
            //
        });
    }
};
