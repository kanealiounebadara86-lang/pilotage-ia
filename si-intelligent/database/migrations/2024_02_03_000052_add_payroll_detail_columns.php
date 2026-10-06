<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->decimal('overtime_amount', 12, 2)->default(0)->after('base_salary');
            $table->decimal('unpaid_leave_deduction', 12, 2)->default(0)->after('bonuses');
            $table->decimal('social_contributions', 12, 2)->default(0)->after('unpaid_leave_deduction');
            $table->decimal('advance_deduction', 12, 2)->default(0)->after('social_contributions');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn(['overtime_amount', 'unpaid_leave_deduction', 'social_contributions', 'advance_deduction']);
        });
    }
};
