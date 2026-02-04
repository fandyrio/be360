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
        Schema::table('tref_pegawai', function (Blueprint $table) {
            $table->string('no_hp')->after('nip');
            $table->string('foto_pegawai')->after('no_hp');
        });
    }

    /**gan15!
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tref_pegawai', function (Blueprint $table) {
            //
        });
    }
};
