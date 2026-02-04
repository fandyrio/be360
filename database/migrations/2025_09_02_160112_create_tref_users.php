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
        Schema::create('tref_users', function (Blueprint $table) {
            $table->id('IdUser');
            $table->string('Uname');
            $table->integer('IdPegawai');
            $table->string('NamaLengkap');
            $table->string('NIPBaru');
            $table->string('IdRole');
            $table->string('Passwd');
            $table->string('Email');
            $table->string('PassTemp')->nullable();
            $table->boolean('PassTemp_activation');
            $table->integer('IdJabatan');
            $table->integer('IdSatker');
            $table->dateTime('diinput_tgl');
            $table->dateTime('diperbaharui_tanggal');
            $table->string('diperbaharui_oleh');
            $table->boolean('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tref_users');
    }
};
