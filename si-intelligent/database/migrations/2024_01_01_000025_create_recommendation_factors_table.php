<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendation_factors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('replenishment_recommendation_id')->constrained()->cascadeOnDelete();
            $table->string('label'); // ex: "Hausse prévue des ventes"
            $table->decimal('weight', 6, 2); // importance du facteur
            $table->enum('direction', ['favorable', 'defavorable'])->default('favorable');
            $table->text('detail')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendation_factors');
    }
};
