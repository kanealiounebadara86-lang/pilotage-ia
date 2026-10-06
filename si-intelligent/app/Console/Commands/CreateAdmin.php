<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\ChartOfAccountSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin {--company=} {--name=} {--email=} {--password=}';

    protected $description = "Installation de production : crée l'entreprise, son plan comptable et le premier administrateur (sans données de démonstration).";

    public function handle(): int
    {
        $companyName = $this->option('company') ?: $this->ask("Nom de l'entreprise");
        $name = $this->option('name') ?: $this->ask("Nom de l'administrateur");
        $email = $this->option('email') ?: $this->ask("Adresse e-mail de l'administrateur");
        $password = $this->option('password') ?: $this->secret('Mot de passe (10 caractères minimum)');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Adresse e-mail invalide.');

            return self::FAILURE;
        }
        if (strlen((string) $password) < 10) {
            $this->error('Le mot de passe doit faire au moins 10 caractères.');

            return self::FAILURE;
        }
        if (User::where('email', $email)->exists()) {
            $this->error('Un utilisateur avec cet e-mail existe déjà.');

            return self::FAILURE;
        }

        $adminRole = Role::where('name', 'admin')->first();
        if (! $adminRole) {
            $this->error("Rôles introuvables : lancez d'abord php artisan db:seed --class=\"Database\\Seeders\\RolePermissionSeeder\".");

            return self::FAILURE;
        }

        DB::transaction(function () use ($companyName, $name, $email, $password, $adminRole) {
            $company = Company::create(['name' => $companyName, 'currency' => 'XOF', 'country' => 'Sénégal']);

            User::create([
                'name' => $name, 'email' => $email, 'password' => Hash::make($password),
                'company_id' => $company->id, 'role_id' => $adminRole->id, 'is_active' => true,
            ]);

            Warehouse::firstOrCreate(['company_id' => $company->id, 'name' => 'Entrepôt principal'], ['is_default' => true]);

            // Le plan comptable doit exister avant toute vente ou réception d'achat.
            Artisan::call('db:seed', ['--class' => ChartOfAccountSeeder::class, '--force' => true]);
        });

        $this->info("✓ Entreprise « {$companyName} » créée, plan comptable initialisé, administrateur {$email} prêt.");

        return self::SUCCESS;
    }
}
