<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountingService
{
    /**
     * Crée une écriture comptable équilibrée (débit = crédit).
     * $lines : [['code' => '701000', 'debit' => 0, 'credit' => 1000, 'label' => '...'], ...]
     * Rejette toute écriture qui ne s'équilibre pas — c'est la règle de base de
     * la comptabilité en partie double, on ne la contourne jamais.
     */
    public function createEntry(
        int $companyId,
        string $label,
        array $lines,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?User $user = null,
    ): JournalEntry {
        $totalDebit = round(collect($lines)->sum('debit'), 2);
        $totalCredit = round(collect($lines)->sum('credit'), 2);

        abort_unless($totalDebit === $totalCredit, 422, "Écriture comptable déséquilibrée : débit {$totalDebit} ≠ crédit {$totalCredit}.");

        return DB::transaction(function () use ($companyId, $label, $lines, $sourceType, $sourceId, $user, $totalDebit) {
            $entry = JournalEntry::create([
                'company_id' => $companyId,
                'reference' => 'ECR-'.strtoupper(Str::random(8)),
                'entry_date' => now()->toDateString(),
                'label' => $label,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'created_by' => $user?->id,
            ]);

            foreach ($lines as $line) {
                $account = ChartOfAccount::where('company_id', $companyId)->where('code', $line['code'])->first();
                abort_if(! $account, 422, "Compte comptable {$line['code']} introuvable — vérifie que le plan comptable a été initialisé (ChartOfAccountSeeder).");

                $entry->lines()->create([
                    'chart_of_account_id' => $account->id,
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'label' => $line['label'] ?? $label,
                ]);
            }

            return $entry->load('lines.account');
        });
    }

    /**
     * Écriture automatique pour une vente confirmée :
     * Débit Clients / Crédit Ventes, ET sortie du stock au coût d'achat :
     * Débit Coût des marchandises vendues (601000) / Crédit Stock (311000).
     */
    public function recordSale(Sale $sale): JournalEntry
    {
        $lines = [
            ['code' => '411000', 'debit' => (float) $sale->total_amount, 'credit' => 0, 'label' => 'Créance client'],
            ['code' => '701000', 'debit' => 0, 'credit' => (float) $sale->total_amount, 'label' => 'Vente de marchandises'],
        ];

        if ((float) $sale->total_cost > 0) {
            $lines[] = ['code' => '601000', 'debit' => (float) $sale->total_cost, 'credit' => 0, 'label' => 'Coût des marchandises vendues'];
            $lines[] = ['code' => '311000', 'debit' => 0, 'credit' => (float) $sale->total_cost, 'label' => 'Sortie de stock'];
        }

        return $this->createEntry(
            companyId: $sale->company_id,
            label: "Vente {$sale->reference}",
            lines: $lines,
            sourceType: Sale::class,
            sourceId: $sale->id,
        );
    }

    /** Annulation d'une vente : contre-passation exacte de l'écriture de vente. */
    public function recordSaleCancellation(Sale $sale): JournalEntry
    {
        $lines = [
            ['code' => '701000', 'debit' => (float) $sale->total_amount, 'credit' => 0, 'label' => 'Annulation vente'],
            ['code' => '411000', 'debit' => 0, 'credit' => (float) $sale->total_amount, 'label' => 'Annulation créance client'],
        ];

        if ((float) $sale->total_cost > 0) {
            $lines[] = ['code' => '311000', 'debit' => (float) $sale->total_cost, 'credit' => 0, 'label' => 'Retour en stock'];
            $lines[] = ['code' => '601000', 'debit' => 0, 'credit' => (float) $sale->total_cost, 'label' => 'Annulation coût des marchandises vendues'];
        }

        return $this->createEntry(
            companyId: $sale->company_id,
            label: "Annulation vente {$sale->reference}",
            lines: $lines,
            sourceType: Sale::class,
            sourceId: $sale->id,
        );
    }

    /**
     * Retour client : la vente diminue (Débit Ventes / Crédit Clients) et, si la
     * marchandise est remise en stock, le stock remonte au coût d'achat.
     */
    public function recordSaleReturn(Sale $sale, float $saleValue, float $costValue, bool $restocked): JournalEntry
    {
        $lines = [
            ['code' => '701000', 'debit' => $saleValue, 'credit' => 0, 'label' => 'Retour client (vente annulée en partie)'],
            ['code' => '411000', 'debit' => 0, 'credit' => $saleValue, 'label' => 'Diminution de la créance client'],
        ];

        if ($restocked && $costValue > 0) {
            $lines[] = ['code' => '311000', 'debit' => $costValue, 'credit' => 0, 'label' => 'Marchandise remise en stock'];
            $lines[] = ['code' => '601000', 'debit' => 0, 'credit' => $costValue, 'label' => 'Annulation du coût des marchandises vendues'];
        }

        return $this->createEntry(
            companyId: $sale->company_id,
            label: "Retour sur vente {$sale->reference}",
            lines: $lines,
            sourceType: Sale::class,
            sourceId: $sale->id,
        );
    }

    /**
     * Écriture automatique pour une réception d'achat :
     * Débit Stock (311000) / Crédit Fournisseurs (401000)
     */
    public function recordPurchaseReceipt(PurchaseOrder $order, float $amount): JournalEntry
    {
        return $this->createEntry(
            companyId: $order->company_id,
            label: "Réception {$order->reference}",
            lines: [
                ['code' => '311000', 'debit' => $amount, 'credit' => 0, 'label' => 'Entrée en stock'],
                ['code' => '401000', 'debit' => 0, 'credit' => $amount, 'label' => 'Dette fournisseur'],
            ],
            sourceType: PurchaseOrder::class,
            sourceId: $order->id,
        );
    }

    /**
     * Grand livre d'un compte : liste chronologique de ses lignes d'écriture,
     * avec le solde progressif (module comptabilité classique).
     */
    public function getLedger(int $companyId, string $accountCode, ?string $from = null, ?string $to = null)
    {
        $account = ChartOfAccount::where('company_id', $companyId)->where('code', $accountCode)->firstOrFail();

        $query = $account->lines()->with('entry')
            ->whereHas('entry', function ($q) use ($from, $to) {
                if ($from) {
                    $q->whereDate('entry_date', '>=', $from);
                }
                if ($to) {
                    $q->whereDate('entry_date', '<=', $to);
                }
            })
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->orderBy('journal_entries.entry_date')
            ->select('journal_entry_lines.*');

        $lines = $query->get();
        $runningBalance = 0;
        $isDebitNormal = in_array($account->class, ['charge', 'actif', 'tresorerie']);

        $ledger = $lines->map(function ($line) use (&$runningBalance, $isDebitNormal) {
            $runningBalance += $isDebitNormal ? ($line->debit - $line->credit) : ($line->credit - $line->debit);

            return [
                'date' => $line->entry->entry_date,
                'reference' => $line->entry->reference,
                'label' => $line->label,
                'debit' => (float) $line->debit,
                'credit' => (float) $line->credit,
                'running_balance' => round($runningBalance, 2),
            ];
        });

        return [
            'account' => ['code' => $account->code, 'label' => $account->label, 'class' => $account->class],
            'final_balance' => round($runningBalance, 2),
            'lines' => $ledger,
        ];
    }
}
