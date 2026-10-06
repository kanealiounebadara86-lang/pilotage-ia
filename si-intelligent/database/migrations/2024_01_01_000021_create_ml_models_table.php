<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ml_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // random_forest, xgboost, baseline_naive, ...
            $table->string('target'); // ex: sales_forecast
            $table->string('version');
            $table->timestamp('trained_at');
            $table->json('training_data_range')->nullable();
            $table->json('metrics')->nullable(); // mae, rmse, mape
            $table->json('parameters')->nullable();
            $table->string('storage_path')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ml_models');
    }
};
