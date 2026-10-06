<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\Company;
use Illuminate\Database\Seeder;

class ChartOfAccountSeeder extends Seeder
{
    /**
     * Plan comptable simplifié (inspiré SYSCOHADA) : suffisant pour journaliser
     * les ventes, achats, charges de personnel et la trésorerie d'une PME.
     * Crée le plan pour CHAQUE entreprise existante.
     */
    public function run(): void
    {
        $accounts = [
            ['code' => '601000', 'label' => 'Achats de marchandises', 'class' => 'charge'],
            ['code' => '607000', 'label' => 'Achats stockés', 'class' => 'charge'],
            ['code' => '621000', 'label' => 'Charges de personnel', 'class' => 'charge'],
            ['code' => '626000', 'label' => 'Frais divers de gestion', 'class' => 'charge'],
            ['code' => '701000', 'label' => 'Ventes de marchandises', 'class' => 'produit'],
            ['code' => '311000', 'label' => 'Stock de marchandises', 'class' => 'actif'],
            ['code' => '411000', 'label' => 'Clients', 'class' => 'actif'],
            ['code' => '401000', 'label' => 'Fournisseurs', 'class' => 'passif'],
            ['code' => '421000', 'label' => 'Personnel — rémunérations dues', 'class' => 'passif'],
            ['code' => '425000', 'label' => 'Avances et acomptes au personnel', 'class' => 'actif'],
            ['code' => '431000', 'label' => 'Sécurité sociale — cotisations à payer', 'class' => 'passif'],
            ['code' => '512000', 'label' => 'Banque', 'class' => 'tresorerie'],
            ['code' => '571000', 'label' => 'Caisse', 'class' => 'tresorerie'],
        ];

        foreach (Company::all() as $company) {
            foreach ($accounts as $account) {
                ChartOfAccount::updateOrCreate(
                    ['company_id' => $company->id, 'code' => $account['code']],
                    ['label' => $account['label'], 'class' => $account['class']]
                );
            }
        }
    }
}
