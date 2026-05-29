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
        Schema::table('trans_observee', function (Blueprint $table) {
            $table->boolean('accessed_link')->default(0)->after('endpoint');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trans_observee', function (Blueprint $table) {
            //
        });
    }
};
