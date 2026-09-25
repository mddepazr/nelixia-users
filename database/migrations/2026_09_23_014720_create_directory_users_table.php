<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('directory_users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 254)->unique();

            $table->foreignId('department_id')
                ->constrained()
                ->restrictOnDelete();

            // Referencia de la imagen recortada almacenada en MinIO.
            $table->string('photo_path');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('directory_users');
    }
};
