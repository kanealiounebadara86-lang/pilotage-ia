<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['cdi', 'cdd', 'stage', 'prestation']);
            $table->date('start_date');
            $table->date('end_date')->nullable(); // null pour un CDI
            $table->decimal('salary', 12, 2);
            $table->string('document_path')->nullable(); // contrat scanné, si uploadé
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_contracts');
    }
};
