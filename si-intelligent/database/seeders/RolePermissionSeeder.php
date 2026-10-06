<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Crée les rôles et permissions de base du système, conformément
     * au module 1 (authentification et utilisateurs) du cahier des charges.
     */
    public function run(): void
    {
        $permissions = [
            // ventes
            ['name' => 'sales.view', 'label' => 'Voir les ventes', 'module' => 'sales'],
            ['name' => 'sales.manage', 'label' => 'Gérer les ventes', 'module' => 'sales'],
            // stocks
            ['name' => 'stock.view', 'label' => 'Voir les stocks', 'module' => 'stock'],
            ['name' => 'stock.manage', 'label' => 'Gérer les stocks', 'module' => 'stock'],
            // achats
            ['name' => 'purchases.view', 'label' => 'Voir les achats', 'module' => 'purchases'],
            ['name' => 'purchases.manage', 'label' => 'Gérer les achats', 'module' => 'purchases'],
            // finance
            ['name' => 'finance.view', 'label' => 'Voir les données financières', 'module' => 'finance'],
            ['name' => 'finance.manage', 'label' => 'Gérer les données financières', 'module' => 'finance'],
            // IA / pilotage
            ['name' => 'ai.view_recommendations', 'label' => 'Voir les recommandations IA', 'module' => 'ai'],
            ['name' => 'ai.decide', 'label' => 'Valider/refuser une recommandation IA', 'module' => 'ai'],
            ['name' => 'ai.use_assistant', 'label' => "Utiliser l'assistant LLM", 'module' => 'ai'],
            // comptabilité
            ['name' => 'accounting.view', 'label' => 'Voir la comptabilité', 'module' => 'accounting'],
            ['name' => 'accounting.manage', 'label' => 'Gérer les écritures comptables', 'module' => 'accounting'],
            // ressources humaines
            ['name' => 'hr.view', 'label' => 'Voir les données RH', 'module' => 'hr'],
            ['name' => 'hr.manage', 'label' => 'Gérer les employés et la paie', 'module' => 'hr'],
            // administration
            ['name' => 'admin.manage_users', 'label' => 'Gérer les utilisateurs', 'module' => 'admin'],
            ['name' => 'admin.manage_company', 'label' => "Gérer les paramètres de l'entreprise", 'module' => 'admin'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['name' => $permission['name']], $permission);
        }

        $roles = [
            'admin' => ['label' => 'Administrateur', 'permissions' => '*'],
            'directeur' => ['label' => 'Directeur / Décideur', 'permissions' => [
                'sales.view', 'stock.view', 'purchases.view', 'finance.view',
                'ai.view_recommendations', 'ai.decide', 'ai.use_assistant',
                'accounting.view', 'hr.view',
            ]],
            'comptable' => ['label' => 'Comptable', 'permissions' => [
                'finance.view', 'finance.manage', 'accounting.view', 'accounting.manage',
            ]],
            'resp_rh' => ['label' => 'Responsable RH', 'permissions' => [
                'hr.view', 'hr.manage',
            ]],
            'resp_commercial' => ['label' => 'Responsable commercial', 'permissions' => [
                'sales.view', 'sales.manage', 'ai.use_assistant',
            ]],
            'resp_stock' => ['label' => 'Responsable stock', 'permissions' => [
                'stock.view', 'stock.manage', 'ai.view_recommendations', 'ai.use_assistant',
            ]],
            'resp_achats' => ['label' => 'Responsable achats', 'permissions' => [
                'purchases.view', 'purchases.manage', 'ai.view_recommendations', 'ai.use_assistant',
            ]],
            'resp_financier' => ['label' => 'Responsable financier', 'permissions' => [
                'finance.view', 'finance.manage', 'ai.use_assistant',
            ]],
        ];

        foreach ($roles as $name => $config) {
            $role = Role::updateOrCreate(['name' => $name], ['label' => $config['label']]);

            $permissionIds = $config['permissions'] === '*'
                ? Permission::pluck('id')
                : Permission::whereIn('name', $config['permissions'])->pluck('id');

            $role->permissions()->sync($permissionIds);
        }
    }
}
