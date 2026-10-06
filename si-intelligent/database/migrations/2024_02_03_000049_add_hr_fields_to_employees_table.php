<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            // Solde de congés payés, en jours. 24 jours/an est une valeur de départ
            // courante (2 jours/mois) — ajustable par employé selon la convention
            // collective applicable, non gérée finement ici.
            $table->decimal('leave_balance_days', 6, 2)->default(24);
            // Taux horaire utilisé pour valoriser les heures supplémentaires.
            // Si null, calculé à la volée : salaire de base / 173.33h (durée légale
            // mensuelle moyenne dans plusieurs pays d'Afrique de l'Ouest).
            $table->decimal('hourly_rate', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['leave_balance_days', 'hourly_rate']);
        });
    }
};
