<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plan comptable simplifié, inspiré du SYSCOHADA (référentiel comptable
        // ouest-africain) mais réduit aux classes utiles à une PME.
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20); // ex: 701000, 601000, 512000
            $table->string('label');
            $table->enum('class', ['charge', 'produit', 'actif', 'passif', 'tresorerie']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
