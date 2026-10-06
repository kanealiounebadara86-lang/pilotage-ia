<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rattachement optionnel d'une vente à une campagne marketing, pour
     * pouvoir calculer le chiffre d'affaires réellement généré par la
     * campagne (et donc son ROI) — module 9 du cahier des charges d'intégration.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->after('customer_id')->constrained('marketing_campaigns')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_id');
        });
    }
};
