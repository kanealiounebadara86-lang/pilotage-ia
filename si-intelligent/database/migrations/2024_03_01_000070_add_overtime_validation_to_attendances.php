<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('attendances', 'overtime_suggested')) {
                // Heures sup calculées automatiquement (au-delà de 8h) : simple suggestion.
                $table->decimal('overtime_suggested', 5, 2)->default(0)->after('overtime_hours');
            }
            if (! Schema::hasColumn('attendances', 'overtime_validated_at')) {
                // overtime_hours = heures sup VALIDÉES par la RH (seules celles-ci vont en paie).
                $table->timestamp('overtime_validated_at')->nullable()->after('overtime_suggested');
            }
        });

        // Les pointages existants gardent leurs heures sup, considérées comme validées.
        DB::table('attendances')->where('overtime_hours', '>', 0)->update([
            'overtime_suggested' => DB::raw('overtime_hours'),
            'overtime_validated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['overtime_suggested', 'overtime_validated_at']);
        });
    }
};
