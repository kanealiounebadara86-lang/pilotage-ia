<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Une écriture comptable = un événement (ex: "Vente VTE-001"),
        // composé de plusieurs lignes équilibrées (débit total = crédit total).
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->date('entry_date');
            $table->string('label');
            $table->string('source_type')->nullable(); // Sale, PurchaseOrder, Expense, Revenue, Payroll...
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
