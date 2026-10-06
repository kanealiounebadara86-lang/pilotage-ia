<?php

namespace App\Services;

use App\Exceptions\VoiceCommandException;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Commandes vocales : une phrase dictée devient une action réelle, avec des garde-fous.
 *
 *  1. interpret()  : le service IA (Gemini) transforme la phrase en intention JSON ;
 *                    Laravel RÉSOUT les noms (produit, client, employé…) dans les données
 *                    de L'ENTREPRISE de l'utilisateur et vérifie SES permissions.
 *  2. Les actions qui engagent de l'argent ou du stock (vente, encaissement, annulation,
 *     pointage groupé) exigent une CONFIRMATION (clic ou "oui" dit à voix haute).
 *     Les créations simples (produit, client, fournisseur), le pointage d'un employé
 *     et la navigation s'exécutent directement.
 *  3. execute()    : exécute par les mêmes services métier que les écrans — donc la même
 *                    synchronisation stock / finance / comptabilité — et revérifie tout :
 *                    le client n'est jamais cru sur parole.
 */
class VoiceCommandService
{
    private const PERMISSIONS = [
        'create_product' => 'stock.manage',
        'create_customer' => 'sales.manage',
        'create_supplier' => 'purchases.manage',
        'record_sale' => 'sales.manage',
        'collect_payment' => 'sales.manage',
        'cancel_sale' => 'sales.manage',
        'punch_employee' => 'hr.manage',
        'punch_all' => 'hr.manage',
    ];

    private const NEEDS_CONFIRMATION = ['record_sale', 'collect_payment', 'cancel_sale', 'punch_all'];

    private const METHOD_LABELS = [
        'especes' => 'espèces', 'mobile_money' => 'mobile money', 'carte' => 'carte', 'virement' => 'virement', 'cheque' => 'chèque',
    ];

    public function __construct(
        private readonly AiGatewayService $gateway,
        private readonly SalesService $salesService,
        private readonly PaymentService $paymentService,
        private readonly AttendanceService $attendanceService,
    ) {
    }

    // ======================= 1. Compréhension et plan =======================

    public function interpret(string $transcript, User $user): array
    {
        Log::info('Commande vocale', ['user_id' => $user->id, 'transcript' => $transcript]);

        $result = $this->gateway->voiceIntent($transcript);
        $intent = $result['intent'] ?? 'unknown';
        $params = $result['params'] ?? [];

        if ($intent === 'unknown') {
            return $this->error($result['message'] ?? "Je n'ai pas compris. Essayez par exemple : « encaisse tout en espèces » ou « ajoute le produit ventilateur à 25000 ».");
        }

        try {
            if ($intent !== 'navigate') {
                $this->authorizeIntent($intent, $user);
            }

            $plan = match ($intent) {
                'create_product' => $this->planCreateProduct($params),
                'create_customer' => $this->planCreateNamed($params, 'client'),
                'create_supplier' => $this->planCreateNamed($params, 'fournisseur'),
                'record_sale' => $this->planRecordSale($params, $user),
                'collect_payment' => $this->planCollect($params, $user),
                'cancel_sale' => $this->planCancel($params, $user),
                'punch_employee' => $this->planPunchEmployee($params, $user),
                'punch_all' => ['summary' => 'Pointer l\'arrivée de tous les employés non pointés (hors congés).', 'payload' => []],
                'navigate' => $this->planNavigate($params, $user),
                default => throw new VoiceCommandException("Je ne sais pas encore faire cela."),
            };
        } catch (VoiceCommandException $e) {
            return $this->error($e->getMessage());
        }

        return [
            'intent' => $intent,
            'summary' => $plan['summary'],
            'payload' => $plan['payload'],
            'needs_confirmation' => in_array($intent, self::NEEDS_CONFIRMATION, true),
        ];
    }

    private function error(string $message): array
    {
        return ['intent' => 'error', 'summary' => $message, 'payload' => [], 'needs_confirmation' => false];
    }

    private function authorizeIntent(string $intent, User $user): void
    {
        $perm = self::PERMISSIONS[$intent] ?? null;
        if ($perm && ! $user->hasPermission($perm)) {
            throw new VoiceCommandException("Votre rôle n'a pas l'autorisation d'effectuer cette action.");
        }
    }

    private function money(float $n): string
    {
        return number_format($n, 0, ',', ' ').' XOF';
    }

    private function planCreateProduct(array $p): array
    {
        $name = trim((string) ($p['name'] ?? ''));
        if ($name === '') {
            throw new VoiceCommandException('Quel est le nom du produit ?');
        }
        $payload = [
            'name' => $name,
            'sale_price' => max(0, (float) ($p['sale_price'] ?? 0)),
            'purchase_price' => max(0, (float) ($p['purchase_price'] ?? 0)),
            'unit' => trim((string) ($p['unit'] ?? '')) ?: 'unite',
            'stock_min' => max(0, (int) ($p['stock_min'] ?? 0)),
        ];

        return ['summary' => "Produit « {$name} » créé (prix de vente ".$this->money($payload['sale_price']).').', 'payload' => $payload];
    }

    private function planCreateNamed(array $p, string $kind): array
    {
        $name = trim((string) ($p['name'] ?? ''));
        if ($name === '') {
            throw new VoiceCommandException("Quel est le nom du {$kind} ?");
        }

        return ['summary' => ucfirst($kind)." « {$name} » ajouté.", 'payload' => ['name' => $name, 'phone' => $p['phone'] ?? null]];
    }

    private function planRecordSale(array $p, User $user): array
    {
        $rawItems = $p['items'] ?? [];
        if (! is_array($rawItems) || count($rawItems) === 0) {
            throw new VoiceCommandException('Quels produits voulez-vous vendre ?');
        }

        $items = [];
        $total = 0.0;
        $parts = [];
        $warnings = [];
        foreach ($rawItems as $row) {
            $product = $this->findProduct((string) ($row['product_name'] ?? ''), $user->company_id);
            $qty = max(1, (int) ($row['quantity'] ?? 1));
            $items[] = ['product_id' => $product->id, 'quantity' => $qty];
            $total += (float) $product->sale_price * $qty;
            $parts[] = "{$qty} × {$product->name}";
            $stock = (int) (StockLevel::where('product_id', $product->id)->sum('quantity_available'));
            if ($stock < $qty) {
                $warnings[] = "stock insuffisant pour {$product->name} ({$stock} disponible)";
            }
        }

        $customer = ! empty($p['customer_name']) ? $this->findCustomer((string) $p['customer_name'], $user->company_id) : null;
        $method = $this->method($p['payment_method'] ?? null);
        $payNow = ! empty($p['pay_now']) || ! empty($p['payment_method']);

        $summary = 'Vente de '.implode(', ', $parts).' pour '.$this->money($total);
        $summary .= $customer ? ", client {$customer->name}" : '';
        $summary .= $payNow ? ', encaissée en '.self::METHOD_LABELS[$method] : ', non encaissée (créance)';
        $summary .= $warnings ? '. Attention : '.implode(' ; ', $warnings) : '';

        return ['summary' => $summary.'.', 'payload' => [
            'items' => $items, 'customer_id' => $customer?->id, 'pay_now' => $payNow, 'payment_method' => $method,
        ]];
    }

    private function planCollect(array $p, User $user): array
    {
        $method = $this->method($p['method'] ?? null);
        $scope = $p['scope'] ?? 'all';
        $query = Sale::where('company_id', $user->company_id)->where('status', 'confirmed')
            ->whereIn('payment_status', ['non_payee', 'partielle']);
        $label = 'toutes les ventes non payées';
        $payload = ['method' => $method, 'customer_id' => null, 'sale_id' => null];

        if ($scope === 'customer') {
            $customer = $this->findCustomer((string) ($p['customer_name'] ?? ''), $user->company_id);
            $query->where('customer_id', $customer->id);
            $payload['customer_id'] = $customer->id;
            $label = "les ventes non payées de {$customer->name}";
        } elseif ($scope === 'sale') {
            $sale = Sale::where('company_id', $user->company_id)->where('reference', strtoupper(trim((string) ($p['sale_reference'] ?? ''))))->first();
            if (! $sale) {
                throw new VoiceCommandException('Je ne trouve pas cette vente.');
            }
            $query->where('id', $sale->id);
            $payload['sale_id'] = $sale->id;
            $label = "la vente {$sale->reference}";
        }

        $sales = $query->get();
        $due = round((float) $sales->sum(fn ($s) => max(0, $s->balanceDue())), 2);
        if ($due <= 0.01) {
            throw new VoiceCommandException('Il n\'y a rien à encaisser pour cette demande.');
        }

        return ['summary' => 'Encaisser '.$label.' : '.$sales->count().' vente(s), '.$this->money($due).' en '.self::METHOD_LABELS[$method].'.', 'payload' => $payload];
    }

    private function planCancel(array $p, User $user): array
    {
        $ref = strtoupper(trim((string) ($p['sale_reference'] ?? '')));
        $query = Sale::where('company_id', $user->company_id)->where('status', 'confirmed');
        $sale = ($ref === '' || $ref === 'LAST') ? $query->latest('id')->first() : $query->where('reference', $ref)->first();
        if (! $sale) {
            throw new VoiceCommandException('Je ne trouve pas de vente à annuler.');
        }

        $paid = $sale->amountPaid();
        $summary = "Annuler la vente {$sale->reference} (".$this->money((float) $sale->total_amount).'). Le stock sera remis';
        $summary .= $paid > 0.01 ? ' et '.$this->money($paid).' remboursé au client.' : '.';

        return ['summary' => $summary, 'payload' => ['sale_id' => $sale->id]];
    }

    private function planPunchEmployee(array $p, User $user): array
    {
        $employee = $this->findEmployee((string) ($p['employee_name'] ?? ''), $user->company_id);
        // On vérifie tout de suite le congé pour répondre clairement à l'oral.
        try {
            $this->attendanceService->assertCanBePresent($employee, now()->toDateString());
        } catch (\DomainException $e) {
            throw new VoiceCommandException($employee->fullName().' : '.$e->getMessage());
        }

        return ['summary' => 'Pointage de '.$employee->fullName().'.', 'payload' => ['employee_id' => $employee->id]];
    }

    private function planNavigate(array $p, User $user): array
    {
        $wanted = $this->norm((string) ($p['page'] ?? ''));
        if ($wanted === '') {
            throw new VoiceCommandException('Quelle page voulez-vous ouvrir ?');
        }

        $candidates = [['label' => 'Tableau de bord', 'url' => route('dashboard')]];
        foreach (config('navigation.sections') as $key => $section) {
            $candidates[] = ['label' => $section['label'], 'url' => route('hub', ['section' => $key])];
            foreach ($section['items'] as $item) {
                $candidates[] = ['label' => $item['label'], 'url' => route($item['route'])];
            }
        }

        foreach ($candidates as $c) {
            $label = $this->norm($c['label']);
            if (str_contains($label, $wanted) || str_contains($wanted, $label)) {
                return ['summary' => "J'ouvre {$c['label']}.", 'payload' => ['url' => $c['url']]];
            }
        }

        throw new VoiceCommandException("Je ne trouve pas la page « {$p['page']} ».");
    }

    // ======================= 2. Exécution =======================

    public function execute(string $intent, array $payload, User $user): array
    {
        if (! in_array($intent, array_merge(array_keys(self::PERMISSIONS), ['navigate']), true)) {
            throw new VoiceCommandException('Action inconnue.');
        }
        if ($intent !== 'navigate') {
            $this->authorizeIntent($intent, $user);
        }

        return match ($intent) {
            'create_product' => $this->doCreateProduct($payload, $user),
            'create_customer' => $this->doCreateCustomer($payload, $user),
            'create_supplier' => $this->doCreateSupplier($payload, $user),
            'record_sale' => $this->doRecordSale($payload, $user),
            'collect_payment' => $this->doCollect($payload, $user),
            'cancel_sale' => $this->doCancel($payload, $user),
            'punch_employee' => $this->doPunch($payload, $user),
            'punch_all' => $this->doPunchAll($user),
            'navigate' => ['message' => 'Page ouverte.', 'redirect' => $payload['url'] ?? route('dashboard')],
        };
    }

    private function doCreateProduct(array $d, User $user): array
    {
        $count = Product::withTrashed()->where('company_id', $user->company_id)->count();
        do {
            $count++;
            $reference = 'PRD-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
        } while (Product::where('reference', $reference)->exists());

        $product = Product::create([
            'company_id' => $user->company_id, 'reference' => $reference, 'name' => $d['name'],
            'unit' => $d['unit'] ?? 'unite', 'purchase_price' => $d['purchase_price'] ?? 0,
            'sale_price' => $d['sale_price'] ?? 0, 'cost' => $d['purchase_price'] ?? 0,
            'stock_min' => $d['stock_min'] ?? 0, 'safety_stock' => 0, 'is_active' => true,
        ]);
        StockLevel::create(['product_id' => $product->id, 'quantity_available' => 0]);

        return ['message' => "Produit {$product->name} créé, référence {$reference}. Son stock est à zéro : il augmentera à la prochaine réception d'achat."];
    }

    private function doCreateCustomer(array $d, User $user): array
    {
        $c = Customer::create(['company_id' => $user->company_id, 'name' => $d['name'], 'phone' => $d['phone'] ?? null, 'type' => 'particulier']);

        return ['message' => "Client {$c->name} ajouté."];
    }

    private function doCreateSupplier(array $d, User $user): array
    {
        $s = Supplier::create(['company_id' => $user->company_id, 'name' => $d['name'], 'phone' => $d['phone'] ?? null, 'is_active' => true]);

        return ['message' => "Fournisseur {$s->name} ajouté."];
    }

    private function doRecordSale(array $d, User $user): array
    {
        $items = [];
        foreach ($d['items'] ?? [] as $row) {
            $product = Product::where('company_id', $user->company_id)->findOrFail((int) $row['product_id']);
            $items[] = ['product_id' => $product->id, 'quantity' => max(1, min(10000, (int) $row['quantity']))];
        }
        if (! $items) {
            throw new VoiceCommandException('Aucun produit à vendre.');
        }

        $customerId = ! empty($d['customer_id']) ? Customer::where('company_id', $user->company_id)->findOrFail((int) $d['customer_id'])->id : null;

        $sale = $this->salesService->createSale([
            'customer_id' => $customerId, 'items' => $items,
            'pay_now' => (bool) ($d['pay_now'] ?? false), 'payment_method' => $this->method($d['payment_method'] ?? null),
        ], $user);

        return ['message' => "Vente {$sale->reference} enregistrée pour ".$this->money((float) $sale->total_amount).($d['pay_now'] ?? false ? ', encaissée.' : ', à encaisser.')];
    }

    private function doCollect(array $d, User $user): array
    {
        $method = $this->method($d['method'] ?? null);

        if (! empty($d['sale_id'])) {
            $sale = Sale::where('company_id', $user->company_id)->findOrFail((int) $d['sale_id']);
            $due = $sale->balanceDue();
            if ($due <= 0.01) {
                throw new VoiceCommandException('Cette vente est déjà entièrement payée.');
            }
            $this->paymentService->recordSalePayment($sale, $due, $method, null, $user);

            return ['message' => "Vente {$sale->reference} encaissée : ".$this->money($due).'.'];
        }

        $customerId = ! empty($d['customer_id']) ? (int) $d['customer_id'] : null;
        $r = $this->paymentService->collectAllUnpaid($user->company_id, $method, $user, $customerId);

        return ['message' => $r['count'].' vente(s) encaissée(s) pour '.$this->money($r['total']).' en '.self::METHOD_LABELS[$method].'.'];
    }

    private function doCancel(array $d, User $user): array
    {
        $sale = Sale::where('company_id', $user->company_id)->findOrFail((int) $d['sale_id']);
        $this->salesService->cancelSale($sale, $user);

        return ['message' => "Vente {$sale->reference} annulée. Stock, chiffre d'affaires et comptabilité sont mis à jour."];
    }

    private function doPunch(array $d, User $user): array
    {
        $employee = Employee::where('company_id', $user->company_id)->findOrFail((int) $d['employee_id']);
        try {
            [, $action] = $this->attendanceService->punch($employee);
        } catch (\DomainException $e) {
            throw new VoiceCommandException($employee->fullName().' : '.$e->getMessage());
        }

        return ['message' => $employee->fullName().($action === 'arrivee' ? ' : arrivée pointée.' : ' : départ pointé.')];
    }

    private function doPunchAll(User $user): array
    {
        $today = now()->toDateString();
        $already = \App\Models\Attendance::where('company_id', $user->company_id)->where('date', $today)->pluck('employee_id');
        $count = 0;
        Employee::where('company_id', $user->company_id)->where('status', 'actif')->whereNotIn('id', $already)->get()
            ->each(function ($e) use (&$count) {
                try {
                    $this->attendanceService->punch($e);
                    $count++;
                } catch (\DomainException $ex) {
                    // en congé : ignoré
                }
            });

        return ['message' => $count.' arrivée(s) pointée(s).'];
    }

    // ======================= Résolution des noms =======================

    private function method(?string $m): string
    {
        return PaymentService::normalizeMethod($m);
    }

    private function norm(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ù' => 'u', 'û' => 'u', 'ç' => 'c']);

        return preg_replace('/[^a-z0-9 ]+/', ' ', $s) ?? $s;
    }

    /** Cherche la meilleure correspondance dans une collection ; erreur claire si aucune ou ambiguë. */
    private function pick($items, string $name, callable $label, string $kind)
    {
        $needle = $this->norm($name);
        if ($needle === '') {
            throw new VoiceCommandException("Quel {$kind} ?");
        }

        $exact = $items->filter(fn ($i) => $this->norm($label($i)) === $needle);
        if ($exact->count() === 1) {
            return $exact->first();
        }

        $tokens = array_filter(explode(' ', $needle));
        $matches = $items->filter(function ($i) use ($label, $tokens) {
            $l = $this->norm($label($i));
            foreach ($tokens as $t) {
                if (! str_contains($l, $t)) {
                    return false;
                }
            }

            return true;
        });

        if ($matches->count() === 1) {
            return $matches->first();
        }
        if ($matches->count() === 0) {
            throw new VoiceCommandException("Je ne trouve aucun {$kind} nommé « {$name} ».");
        }

        $names = $matches->take(4)->map($label)->implode(', ');
        throw new VoiceCommandException("Plusieurs correspondances pour « {$name} » : {$names}. Soyez plus précis.");
    }

    private function findProduct(string $name, int $companyId): Product
    {
        return $this->pick(Product::where('company_id', $companyId)->where('is_active', true)->get(), $name, fn ($p) => $p->name, 'produit');
    }

    private function findCustomer(string $name, int $companyId): Customer
    {
        return $this->pick(Customer::where('company_id', $companyId)->get(), $name, fn ($c) => $c->name, 'client');
    }

    private function findEmployee(string $name, int $companyId): Employee
    {
        return $this->pick(Employee::where('company_id', $companyId)->where('status', 'actif')->get(), $name, fn ($e) => $e->fullName(), 'employé');
    }
}
