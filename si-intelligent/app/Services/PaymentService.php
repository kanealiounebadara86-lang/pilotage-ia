<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public const METHODS = ['especes', 'mobile_money', 'virement', 'cheque', 'carte'];

    public function __construct(
        private readonly AccountingService $accountingService,
        private readonly FinanceService $financeService,
    ) {
    }

    public static function normalizeMethod(?string $method): string
    {
        return in_array($method, self::METHODS, true) ? $method : 'especes';
    }

    private function treasuryAccount(string $method): string
    {
        return in_array($method, ['virement', 'mobile_money', 'carte']) ? '512000' : '571000';
    }

    /**
     * Encaissement d'une vente. La vente a déjà généré son écriture
     * "Débit Clients / Crédit Ventes" à la confirmation (comptabilité
     * d'engagement). Le paiement solde la créance client et fait entrer
     * l'argent en trésorerie ET dans le chiffre d'affaires encaissé.
     */
    public function recordSalePayment(Sale $sale, float $amount, string $method, ?string $date, User $user): Payment
    {
        return DB::transaction(function () use ($sale, $amount, $method, $date, $user) {
            abort_if($sale->status === 'cancelled', 422, 'Cette vente est annulée : aucun encaissement possible.');

            $remaining = $sale->balanceDue();
            abort_if($amount > $remaining + 0.01, 422, "Le montant dépasse le solde restant dû ({$remaining}).");

            $payment = Payment::create([
                'company_id' => $sale->company_id,
                'payable_type' => Sale::class,
                'payable_id' => $sale->id,
                'amount' => $amount,
                'method' => $method,
                'payment_date' => $date ?? now()->toDateString(),
                'user_id' => $user->id,
            ]);

            $this->accountingService->createEntry(
                companyId: $sale->company_id,
                label: "Encaissement {$sale->reference}",
                lines: [
                    ['code' => $this->treasuryAccount($method), 'debit' => $amount, 'credit' => 0, 'label' => 'Encaissement client'],
                    ['code' => '411000', 'debit' => 0, 'credit' => $amount, 'label' => 'Règlement créance client'],
                ],
                sourceType: Payment::class,
                sourceId: $payment->id,
                user: $user,
            );

            $this->financeService->recordSaleCollection($payment, $sale);
            $this->refreshSaleStatus($sale);

            return $payment;
        });
    }

    /**
     * Remboursement d'argent à un client (retour, annulation) : paiement négatif,
     * qui diminue le chiffre d'affaires encaissé et la trésorerie.
     */
    public function refundSalePayment(Sale $sale, float $amount, string $method, User $user, string $label): Payment
    {
        return DB::transaction(function () use ($sale, $amount, $method, $user, $label) {
            $payment = Payment::create([
                'company_id' => $sale->company_id,
                'payable_type' => Sale::class,
                'payable_id' => $sale->id,
                'amount' => -$amount,
                'method' => $method,
                'payment_date' => now()->toDateString(),
                'user_id' => $user->id,
            ]);

            $this->accountingService->createEntry(
                companyId: $sale->company_id,
                label: $label,
                lines: [
                    ['code' => '411000', 'debit' => $amount, 'credit' => 0, 'label' => 'Remboursement client'],
                    ['code' => $this->treasuryAccount($method), 'debit' => 0, 'credit' => $amount, 'label' => 'Sortie de trésorerie'],
                ],
                sourceType: Payment::class,
                sourceId: $payment->id,
                user: $user,
            );

            $this->financeService->recordSaleRefund($payment, $sale, $label);
            $this->refreshSaleStatus($sale);

            return $payment;
        });
    }

    /**
     * Encaisse en une fois toutes les ventes (ou celles d'un client) qui ont
     * un solde dû — utilisé par "Encaisser tout" et par la commande vocale.
     *
     * @return array{count:int, total:float, references:array<int,string>}
     */
    public function collectAllUnpaid(int $companyId, string $method, User $user, ?int $customerId = null): array
    {
        return DB::transaction(function () use ($companyId, $method, $user, $customerId) {
            $query = Sale::where('company_id', $companyId)->where('status', 'confirmed')
                ->whereIn('payment_status', ['non_payee', 'partielle']);
            if ($customerId) {
                $query->where('customer_id', $customerId);
            }

            $count = 0;
            $total = 0.0;
            $references = [];
            foreach ($query->orderBy('sale_date')->get() as $sale) {
                $due = $sale->balanceDue();
                if ($due > 0.01) {
                    $this->recordSalePayment($sale, $due, $method, null, $user);
                    $count++;
                    $total += $due;
                    $references[] = $sale->reference;
                }
            }

            return ['count' => $count, 'total' => round($total, 2), 'references' => $references];
        });
    }

    /**
     * Règlement d'un fournisseur : Débit Fournisseurs / Crédit Banque/Caisse.
     * La dépense d'achat n'entre dans les indicateurs financiers qu'à ce moment.
     */
    public function recordPurchasePayment(PurchaseOrder $order, float $amount, string $method, ?string $date, User $user): Payment
    {
        return DB::transaction(function () use ($order, $amount, $method, $date, $user) {
            $remaining = $order->balanceDue();
            abort_if($amount > $remaining + 0.01, 422, "Le montant dépasse le solde restant dû ({$remaining}).");

            $payment = Payment::create([
                'company_id' => $order->company_id,
                'payable_type' => PurchaseOrder::class,
                'payable_id' => $order->id,
                'amount' => $amount,
                'method' => $method,
                'payment_date' => $date ?? now()->toDateString(),
                'user_id' => $user->id,
            ]);

            $this->accountingService->createEntry(
                companyId: $order->company_id,
                label: "Règlement {$order->reference}",
                lines: [
                    ['code' => '401000', 'debit' => $amount, 'credit' => 0, 'label' => 'Règlement dette fournisseur'],
                    ['code' => $this->treasuryAccount($method), 'debit' => 0, 'credit' => $amount, 'label' => 'Sortie de trésorerie'],
                ],
                sourceType: Payment::class,
                sourceId: $payment->id,
                user: $user,
            );

            $this->financeService->recordPurchasePaymentExpense($payment, $order);
            $order->update(['payment_status' => $this->computeStatus((float) $order->total_amount, $order->amountPaid())]);

            return $payment;
        });
    }

    private function refreshSaleStatus(Sale $sale): void
    {
        $sale->refresh();
        $sale->update(['payment_status' => $this->computeStatus($sale->netTotal(), $sale->amountPaid())]);
    }

    private function computeStatus(float $total, float $paid): string
    {
        if ($paid <= 0.01) {
            return 'non_payee';
        }

        return $paid >= $total - 0.01 ? 'payee' : 'partielle';
    }
}
