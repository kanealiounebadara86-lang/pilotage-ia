<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('replenishment_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('decision_type'); // commander, ne_pas_commander, reduire, augmenter_securite...
            $table->integer('recommended_quantity')->nullable();
            $table->date('recommended_date')->nullable();
            $table->enum('priority', ['critique', 'elevee', 'moyenne', 'faible'])->default('moyenne');
            $table->decimal('confidence', 5, 2)->nullable();
            $table->decimal('estimated_impact', 12, 2)->nullable();
            $table->enum('status', ['proposee', 'acceptee', 'modifiee', 'refusee', 'executee'])->default('proposee');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replenishment_recommendations');
    }
};
