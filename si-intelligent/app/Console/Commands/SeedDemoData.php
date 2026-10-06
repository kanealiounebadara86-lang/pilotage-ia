<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\AttendanceService;
use App\Services\LeaveService;
use App\Services\PaymentService;
use App\Services\PurchaseService;
use App\Services\SalesService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SeedDemoData extends Command
{
    protected $signature = 'app:seed-demo';
    protected $description = "Remplit la base avec des données réalistes : entreprise, employés (pointage/congés sur 1 mois), produits électroménager/téléphonie, fournisseurs, stock et historique de ventes.";

    public function handle(
        AttendanceService $attendanceService,
        LeaveService $leaveService,
        PurchaseService $purchaseService,
        SalesService $salesService,
        PaymentService $paymentService,
    ): int {
        $this->info('Démarrage du remplissage de la base de données…');

        DB::transaction(function () use ($attendanceService, $leaveService, $purchaseService, $salesService, $paymentService) {

            // ===== 1. Entreprise + utilisateur admin =====
            $company = Company::firstOrCreate(
                ['name' => 'Mon Entreprise Test'],
                ['currency' => 'XOF', 'country' => 'Sénégal']
            );

            $adminRole = Role::where('name', 'admin')->first();
            $user = User::firstOrCreate(
                ['email' => 'test@test.com'],
                [
                    'name' => 'Test', 'password' => Hash::make('password'),
                    'company_id' => $company->id, 'role_id' => $adminRole?->id, 'is_active' => true,
                ]
            );
            if ($user->company_id !== $company->id) {
                $user->update(['company_id' => $company->id, 'role_id' => $adminRole?->id]);
            }
            $this->line("✓ Entreprise « {$company->name} » et utilisateur test@test.com prêts.");

            // Le plan comptable DOIT exister avant toute réception d'achat ou vente
            // (AccountingService en a besoin pour générer les écritures automatiques).
            Artisan::call('db:seed', ['--class' => \Database\Seeders\ChartOfAccountSeeder::class, '--force' => true]);
            $this->line('✓ Plan comptable initialisé pour cette entreprise.');

            // ===== 2. Entrepôt par défaut =====
            $warehouse = Warehouse::firstOrCreate(
                ['company_id' => $company->id, 'name' => 'Entrepôt principal'],
                ['is_default' => true]
            );

            // ===== 3. Départements =====
            $departments = collect(['Vente', 'Logistique', 'Direction', 'Support technique'])
                ->mapWithKeys(fn ($name) => [$name => Department::firstOrCreate(['company_id' => $company->id, 'name' => $name])]);
            $this->line('✓ 4 départements créés.');

            // ===== 4. 10 employés + contrats + pointage 1 mois + congés =====
            $employeesData = [
                ['Awa', 'Diop', 'Vendeuse', 'Vente', 120000],
                ['Moussa', 'Ndiaye', 'Vendeur', 'Vente', 115000],
                ['Fatou', 'Sow', 'Responsable magasin', 'Vente', 180000],
                ['Ibrahima', 'Fall', 'Magasinier', 'Logistique', 100000],
                ['Aissatou', 'Ba', 'Magasinière', 'Logistique', 100000],
                ['Cheikh', 'Gueye', 'Livreur', 'Logistique', 90000],
                ['Mariama', 'Diallo', 'Comptable', 'Direction', 200000],
                ['Ousmane', 'Cissé', 'Directeur adjoint', 'Direction', 350000],
                ['Khady', 'Sarr', 'Technicienne SAV', 'Support technique', 130000],
                ['Alioune', 'Kane', 'Chef', 'Direction', 200000],
            ];

            $employees = [];
            foreach ($employeesData as [$first, $last, $position, $deptName, $salary]) {
                $employees[] = Employee::firstOrCreate(
                    ['company_id' => $company->id, 'first_name' => $first, 'last_name' => $last],
                    [
                        'department_id' => $departments[$deptName]->id,
                        'position' => $position,
                        'hire_date' => Carbon::now()->subMonths(rand(2, 24))->toDateString(),
                        'base_salary' => $salary,
                        'status' => 'actif',
                        'leave_balance_days' => 24,
                    ]
                );
            }
            $this->line('✓ 10 employés créés avec contrats implicites.');

            // Pointage réaliste sur les 30 derniers jours (jours ouvrés uniquement)
            $bar = $this->output->createProgressBar(count($employees));
            $bar->start();
            foreach ($employees as $employee) {
                $day = Carbon::now()->subDays(30);
                while ($day->lte(Carbon::yesterday())) {
                    if (! $day->isWeekend()) {
                        $lateChance = rand(1, 100) <= 15; // 15% de chance de retard
                        $overtimeChance = rand(1, 100) <= 25; // 25% de chance d'heures sup
                        $absentChance = rand(1, 100) <= 4; // 4% de chance d'absence non justifiée

                        if (! $absentChance) {
                            $clockIn = $lateChance
                                ? sprintf('%02d:%02d', 8, rand(15, 45))
                                : sprintf('%02d:%02d', 7, rand(50, 59));
                            $clockOut = $overtimeChance
                                ? sprintf('%02d:%02d', rand(19, 20), rand(0, 59))
                                : sprintf('%02d:%02d', 17, rand(0, 30));

                            $attendanceService->clockInOut($employee, $day->toDateString(), $clockIn, $clockOut, true);
                        }
                    }
                    $day->addDay();
                }
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
            $this->line('✓ Pointage généré sur 30 jours pour chaque employé.');

            // Congés payés pour 3 employés, approuvés (met à jour solde + calendrier automatiquement)
            $leaveTargets = [$employees[0], $employees[3], $employees[8]];
            foreach ($leaveTargets as $employee) {
                $start = Carbon::now()->subDays(rand(10, 20));
                // Le congé remplace d'éventuels pointages générés sur ces jours (cohérence présence/congé).
                \App\Models\Attendance::where('employee_id', $employee->id)
                    ->whereBetween('date', [$start->toDateString(), $start->copy()->addDays(2)->toDateString()])->delete();
                $leave = $leaveService->requestLeave([
                    'employee_id' => $employee->id,
                    'type' => 'conges_payes',
                    'start_date' => $start->toDateString(),
                    'end_date' => $start->copy()->addDays(2)->toDateString(),
                    'reason' => 'Congés',
                ]);
                $leaveService->review($leave, 'approuvee', $user);
            }
            $this->line('✓ 3 demandes de congés payés approuvées (solde et calendrier mis à jour).');

            // ===== 5. Fournisseurs =====
            $suppliers = collect([
                ['Sénégal Electro Import', 'M. Fall', '77 111 22 33'],
                ['Dakar Mobile Distribution', 'Mme Diagne', '78 222 33 44'],
            ])->map(fn ($s) => Supplier::firstOrCreate(
                ['company_id' => $company->id, 'name' => $s[0]],
                ['contact_name' => $s[1], 'phone' => $s[2], 'is_active' => true]
            ));
            $suppliers->each(fn ($s) => $s->recalculateGlobalScore());
            $this->line('✓ 2 fournisseurs créés.');

            // ===== 6. Produits électroménager + téléphonie =====
            $productsData = [
                // [nom, unité, prix achat, prix vente, stock_min, quantité reçue]
                ['Réfrigérateur 250L', 'unite', 150000, 210000, 3, 12],
                ['Climatiseur split 1.5CV', 'unite', 180000, 250000, 2, 8],
                ['Téléviseur LED 43"', 'unite', 120000, 165000, 4, 15],
                ['Machine à laver 7kg', 'unite', 130000, 180000, 2, 10],
                ['Micro-ondes 25L', 'unite', 35000, 55000, 5, 20],
                ['Ventilateur sur pied', 'unite', 15000, 25000, 8, 30],
                ['iPhone 13 128Go', 'unite', 380000, 480000, 3, 10],
                ['Samsung Galaxy A54', 'unite', 180000, 240000, 4, 15],
                ['Redmi Note 12', 'unite', 90000, 130000, 5, 25],
                ['Tecno Camon 20', 'unite', 75000, 110000, 5, 20],
                ['Infinix Hot 30', 'unite', 60000, 90000, 6, 25],
                ['Écouteurs Bluetooth', 'unite', 5000, 12000, 10, 40],
                ['Chargeur rapide 25W', 'unite', 2500, 6000, 15, 50],
                ['Powerbank 10000mAh', 'unite', 6000, 13000, 10, 35],
                ['Enceinte Bluetooth portable', 'unite', 12000, 22000, 8, 25],
            ];

            $products = [];
            foreach ($productsData as [$name, $unit, $buy, $sell, $min, $qty]) {
                $existing = Product::where('company_id', $company->id)->where('name', $name)->first();
                if ($existing) {
                    $products[$name] = ['product' => $existing, 'receive_qty' => $qty];
                    continue;
                }

                $count = Product::withTrashed()->where('company_id', $company->id)->count() + 1;
                $reference = 'PRD-'.str_pad($count, 4, '0', STR_PAD_LEFT);
                while (Product::where('reference', $reference)->exists()) {
                    $count++;
                    $reference = 'PRD-'.str_pad($count, 4, '0', STR_PAD_LEFT);
                }

                $product = Product::create([
                    'company_id' => $company->id, 'reference' => $reference, 'name' => $name,
                    'unit' => $unit, 'purchase_price' => $buy, 'sale_price' => $sell, 'cost' => $buy,
                    'stock_min' => $min, 'safety_stock' => max(1, intdiv($min, 2)), 'is_active' => true,
                ]);
                \App\Models\StockLevel::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity_available' => 0]);

                $products[$name] = ['product' => $product, 'receive_qty' => $qty];
            }
            $this->line('✓ 15 produits électroménager/téléphonie créés.');

            // ===== 7. Réception de stock via commande fournisseur (impact stock + finance + comptabilité automatiques) =====
            $supplierCycle = $suppliers->values();
            $i = 0;
            foreach ($products as $name => $data) {
                $supplier = $supplierCycle[$i % $supplierCycle->count()];
                $order = $purchaseService->createPurchaseOrder([
                    'supplier_id' => $supplier->id,
                    'items' => [['product_id' => $data['product']->id, 'quantity' => $data['receive_qty']]],
                ], $user);

                $purchaseService->receiveOrder($order, [
                    ['purchase_item_id' => $order->items->first()->id, 'quantity_received' => $data['receive_qty']],
                ], $user);

                // Règlement fournisseur : la plupart payés, certains en partie (dette ouverte).
                $order->refresh();
                $share = $i % 5 === 4 ? 0.5 : ($i % 7 === 6 ? 0 : 1);
                if ($share > 0) {
                    $paymentService->recordPurchasePayment($order, round((float) $order->total_amount * $share, 2), 'virement', null, $user);
                }
                $i++;
            }
            $this->line('✓ Stock initial reçu pour tous les produits (via de vraies commandes fournisseurs).');

            // ===== 8. Client de démonstration + historique de ventes sur 45 jours =====
            $customer = Customer::firstOrCreate(
                ['company_id' => $company->id, 'name' => 'Client Boutique Plateau'],
                ['type' => 'entreprise', 'phone' => '77 555 66 77']
            );

            $productList = collect($products)->values();
            $salesDays = 45;
            $this->info("Génération de l'historique de ventes sur {$salesDays} jours…");
            $bar2 = $this->output->createProgressBar($salesDays);
            $bar2->start();

            for ($d = $salesDays; $d >= 1; $d--) {
                $date = Carbon::now()->subDays($d);
                $salesToday = rand(0, 3); // 0 à 3 ventes par jour, réalisme d'une petite boutique

                for ($s = 0; $s < $salesToday; $s++) {
                    $product = $productList->random()['product'];
                    $qty = rand(1, 3);

                    // Ne vend que si le stock le permet (évite le négatif pendant la génération)
                    $stock = $product->fresh()->stockLevel;
                    if (! $stock || $stock->quantity_available < $qty) {
                        continue;
                    }

                    try {
                        $sale = $salesService->createSale([
                            'customer_id' => rand(1, 3) === 1 ? $customer->id : null,
                            'sale_date' => $date->toDateString(),
                            'items' => [['product_id' => $product->id, 'quantity' => $qty]],
                        ], $user);

                        // Encaissement : 80 % payées intégralement le jour même, 10 % partiellement, 10 % à crédit.
                        $roll = rand(1, 100);
                        $method = ['especes', 'mobile_money', 'virement', 'carte'][array_rand(['especes', 'mobile_money', 'virement', 'carte'])];
                        if ($roll <= 80) {
                            $paymentService->recordSalePayment($sale, (float) $sale->total_amount, $method, $date->toDateString(), $user);
                        } elseif ($roll <= 90) {
                            $paymentService->recordSalePayment($sale, round((float) $sale->total_amount / 2, 2), $method, $date->toDateString(), $user);
                        }
                    } catch (\Throwable $e) {
                        continue; // ignore une vente qui échouerait (ex: stock devenu insuffisant entre-temps)
                    }
                }
                $bar2->advance();
            }
            $bar2->finish();
            $this->newLine();
            $this->line('✓ Historique de ventes généré (45 jours) — suffisant pour tester la prévision ML.');
        });

        $this->newLine();
        $this->info('Base de données remplie avec succès.');

        return self::SUCCESS;
    }
}
