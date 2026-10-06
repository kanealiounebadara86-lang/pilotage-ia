<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->enum('payment_status', ['non_payee', 'partielle', 'payee'])->default('non_payee')->after('status');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->enum('payment_status', ['non_payee', 'partielle', 'payee'])->default('non_payee')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('payment_status');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('payment_status');
        });
    }
};
