<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // lien optionnel vers un compte utilisateur
            $table->string('first_name');
            $table->string('last_name');
            $table->string('position'); // poste occupé
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->date('hire_date');
            $table->decimal('base_salary', 12, 2);
            $table->enum('status', ['actif', 'suspendu', 'sorti'])->default('actif');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
