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
        Schema::table('budgets', function (Blueprint $table) {
            // Añade la columna account_id como un entero grande sin signo
            // y que no permita nulos. Es importante que el tipo de dato
            // coincida con el id de la tabla 'accounts'.
            // Asegúrate de que tu tabla 'accounts' ya existe.
            $table->unsignedBigInteger('account_id')->after('category_id')->nullable(); 
            // Lo ponemos como nullable temporalmente para poder luego actualizar los valores existentes
            // si los hubiera, aunque ahora mismo la tabla está vacía.
            // Lo pondremos a NOT NULL en una segunda fase si es necesario o desde phpmyadmin
            // después de rellenar valores, pero no es necesario si la tabla está vacía y la FK lo pide.

            // Añade la clave foránea (foreign key)
            $table->foreign('account_id')
                  ->references('id')->on('accounts')
                  ->onDelete('cascade'); // O 'restrict' si prefieres no borrar presupuestos al borrar cuentas
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            // Elimina la clave foránea primero
            $table->dropForeign(['account_id']);
            // Luego elimina la columna
            $table->dropColumn('account_id');
        });
    }
};