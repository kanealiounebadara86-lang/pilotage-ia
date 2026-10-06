<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Précise le motif quand le statut est "congé" (ex: "Congés payés",
            // "Maladie") — visible directement dans le calendrier de présence,
            // sans devoir recroiser avec la table leave_requests.
            $table->string('note')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
