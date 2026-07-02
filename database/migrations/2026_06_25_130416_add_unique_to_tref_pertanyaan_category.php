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
        Schema::table('tref_pertanyaan_category', function (Blueprint $table) {
            $table->unique(['category_id', 'id_pertanyaan'], 'uq_pertanyaan_cat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tref_pertanyaan_category', function (Blueprint $table) {
            //
        });
    }
};
