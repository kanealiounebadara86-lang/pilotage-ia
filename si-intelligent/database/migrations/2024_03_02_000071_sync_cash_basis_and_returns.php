<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'returned_amount')) {
                // Valeur des marchandises retournées par le client (réduit le net à encaisser).
                $table->decimal('returned_amount', 14, 2)->default(0)->after('total_amount');
            }
        });

        // Les transactions financières deviennent le reflet des FLUX D'ARGENT réels :
        // revenu = encaissement client, dépense = règlement fournisseur / paie payée /
        // remboursement. On reconstruit donc depuis les paiements existants.
        DB::table('financial_transactions')->where('category', 'vente')->where('reference_type', 'App\\Models\\Sale')->delete();
        DB::table('financial_transactions')->where('category', 'achat')->where('reference_type', 'App\\Models\\PurchaseOrder')->delete();
        DB::table('financial_transactions')->where('category', 'paie')->where('reference_type', 'App\\Models\\PayrollRun')->delete();

        $now = now();
        foreach (DB::table('payments')->orderBy('id')->get() as $p) {
            $isSale = $p->payable_type === 'App\\Models\\Sale';
            $ref = $isSale
                ? DB::table('sales')->where('id', $p->payable_id)->value('reference')
                : DB::table('purchase_orders')->where('id', $p->payable_id)->value('reference');
            $amount = (float) $p->amount;

            DB::table('financial_transactions')->insert([
                'company_id' => $p->company_id,
                'type' => $isSale ? ($amount >= 0 ? 'revenu' : 'depense') : 'depense',
                'amount' => abs($amount),
                'category' => $isSale ? ($amount >= 0 ? 'vente' : 'remboursement') : 'achat',
                'reference_type' => 'App\\Models\\Payment',
                'reference_id' => $p->id,
                'transaction_date' => $p->payment_date,
                'description' => ($isSale ? 'Encaissement vente ' : 'Règlement commande ').$ref,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (DB::table('payroll_runs')->where('status', 'payee')->get() as $run) {
            DB::table('financial_transactions')->insert([
                'company_id' => $run->company_id, 'type' => 'depense', 'amount' => $run->total_amount,
                'category' => 'paie', 'reference_type' => 'App\\Models\\PayrollRun', 'reference_id' => $run->id,
                'transaction_date' => $run->payment_date, 'description' => "Paie {$run->period}",
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('returned_amount');
        });
    }
};
