<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // rupture, surstock, baisse_ventes, retard_fournisseur, baisse_marge...
            $table->enum('severity', ['critique', 'elevee', 'moyenne', 'faible'])->default('moyenne');
            $table->string('reference_type')->nullable(); // Product, Supplier...
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('message');
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
