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
        Schema::table('trans_pertanyaan_periode', function (Blueprint $table) {
            $table->string('bundle_code_jawaban')->after('id_pertanyaan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trans_pertanyaan_periode', function (Blueprint $table) {
            //
        });
    }
};
