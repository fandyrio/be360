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
        Schema::create('tref_jawaban_bundle', function (Blueprint $table) {
            $table->id();
            $table->string('bundle_code');
            $table->string('bundle_name');
            $table->string('jawaban_text');
            $table->string('point_jawaban');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tref_jawaban_bundle');
    }
};
