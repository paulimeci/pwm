<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lavazho_kryej_operacionet', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_operatori');
            $table->string('targa', 20)->index();
            $table->unsignedBigInteger('id_operacionit');      // shërbimi (lavazho_larjet_lista)
            $table->unsignedBigInteger('mjeti_kategoria_id');  // kategorite_e_mjeteve
            $table->unsignedBigInteger('id_monedha');
            $table->decimal('vlera', 10, 2)->default(0);
            $table->enum('status', ['prezent', 'larguar'])->default('prezent');
            $table->enum('pagesa', ['po', 'jo'])->default('jo');
            $table->timestamp('nisja')->useCurrent();
            $table->timestamp('ikja')->nullable();
            $table->timestamps();

            $table->index(['status', 'pagesa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lavazho_kryej_operacionet');
    }
};
