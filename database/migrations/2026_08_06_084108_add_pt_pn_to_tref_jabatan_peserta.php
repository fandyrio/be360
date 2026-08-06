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
            $table->boolean("pt")->after("ada_plt")->default(true);
            $table->boolean("pn")->after("pt")->default(true);
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
