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
        Schema::create('tref_pertanyaan', function (Blueprint $table) {
            $table->id();
            $table->integer('id_variable');
            $table->string('pertanyaan');
            $table->string('bundle_code_jawaban');
            $table->integer('bobot');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tref_pertanyaan');
    }
};
