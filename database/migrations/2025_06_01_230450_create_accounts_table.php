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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id(); // PK de la tabla, por convención de Laravel
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Clave foránea al usuario
            $table->string('name', 100); // Nombre de la cuenta
            $table->decimal('current_balance', 15, 2)->default(0.00); // Saldo actual, renombrado
            $table->string('currency', 3)->default('EUR'); // Moneda de la cuenta
            $table->enum('type', ['checking', 'savings', 'credit_card', 'investment', 'cash', 'other']); // Tipo de cuenta (ENUM)
            $table->boolean('is_active')->default(true); // Si la cuenta está activa
            // Si quieres 'initial_balance' y 'institution', puedes añadirlos aquí:
            // $table->decimal('initial_balance', 15, 2)->nullable();
            // $table->string('institution', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};