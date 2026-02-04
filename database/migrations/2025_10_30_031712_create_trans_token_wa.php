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
        Schema::create('trans_token_wa', function (Blueprint $table) {
            $table->id();
            $table->integer('id_satker');
            $table->string('category');
            $table->string('payload');
            $table->integer('token');
            $table->datetime('expired_at');
            $table->boolean('status')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trans_token_wa');
    }
};
