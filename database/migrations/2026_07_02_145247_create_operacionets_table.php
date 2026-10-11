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
        Schema::create('adm_operacionet', function (Blueprint $table) {
            $table->id();
            $table->integer('id_operatori');
            $table->string('targa', 20);
            $table->foreignId('id_kategoria_mjeti')
                ->nullable()->constrained('kategorite_e_mjeteve')
                ->nullOnDelete();
            $table->dateTime('nisja');
            $table->dateTime('ikja')->nullable();
            $table->enum('status', ['prezent', 'larguar'])->default('prezent');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adm_operacionet');
    }
};
