<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Version robuste et rejouable : chaque étape vérifie l'état actuel avant
     * d'agir, pour supporter le cas où une tentative précédente aurait
     * partiellement échoué (ex: nom d'index unique différent selon la version
     * de MySQL/MariaDB).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('stock_levels', 'warehouse_id')) {
            Schema::table('stock_levels', function (Blueprint $table) {
                $table->foreignId('warehouse_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            });
        }

        // Supprime tout index unique existant sur product_id seul, quel que soit
        // son nom exact (il peut varier selon la version de MySQL/MariaDB).
        $singleColumnUniques = DB::select("SHOW INDEX FROM stock_levels WHERE Column_name = 'product_id' AND Non_unique = 0 AND Key_name != 'PRIMARY'");
        foreach ($singleColumnUniques as $index) {
            // On ne supprime que les index composés d'une seule colonne (product_id seul).
            $columnsInIndex = DB::select("SHOW INDEX FROM stock_levels WHERE Key_name = ?", [$index->Key_name]);
            if (count($columnsInIndex) === 1) {
                DB::statement("ALTER TABLE stock_levels DROP INDEX `{$index->Key_name}`");
            }
        }

        $compositeExists = DB::select("SHOW INDEX FROM stock_levels WHERE Key_name = 'stock_levels_product_id_warehouse_id_unique'");
        if (empty($compositeExists)) {
            Schema::table('stock_levels', function (Blueprint $table) {
                $table->unique(['product_id', 'warehouse_id']);
            });
        }

        if (! Schema::hasColumn('stock_movements', 'warehouse_id')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->foreignId('warehouse_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('stock_levels', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'warehouse_id']);
            $table->dropConstrainedForeignId('warehouse_id');
            $table->unique('product_id');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });
    }
};
