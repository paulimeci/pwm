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
        Schema::create('lavazho_larjet_cmimi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_sherbimit');
            $table->unsignedBigInteger('id_monedhes');
            $table->unsignedBigInteger('id_kategoria_mjetit');
            $table->decimal('vlera', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lavazho_larjet_cmimi');
    }
};
