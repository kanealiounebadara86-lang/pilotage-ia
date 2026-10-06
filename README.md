# Pilotage.IA — Système d'information intelligent d'aide au pilotage de l'entreprise

Projet de mémoire de Master 2 MIAGE (Université Gaston Berger, Saint-Louis).

> **Conception et développement d'un système d'information intelligent fondé sur l'intelligence artificielle
> pour l'aide au pilotage de l'entreprise : prévision des ventes, optimisation des stocks,
> réapprovisionnement et pilotage financier.**

## Architecture

```
Navigateur  ──►  Laravel 11 (si-intelligent)  ──►  MySQL
                      │
                      └──►  Service Python FastAPI (ai-service)  ──►  Gemini (assistant et commandes vocales)
```

| Dossier | Rôle |
|---|---|
| `si-intelligent/` | Application Laravel : API REST (Sanctum), interface web (Blade + Tailwind + Alpine), logique métier |
| `ai-service/` | Service Python : prévision des ventes, réapprovisionnement, assistant LLM, compréhension vocale |

## Fonctionnalités

- **Gestion** : produits, clients, fournisseurs, ventes, devis, retours/SAV, achats, stocks multi-entrepôts, alertes.
- **Finance et comptabilité** : trésorerie, encaissements/remboursements, plan comptable, écritures en partie double, grand livre.
- **RH** : employés, pointage, heures supplémentaires validées par la RH, congés, avances, paie et bulletins.
- **Marketing** : campagnes et retour sur investissement calculé depuis les ventes encaissées.
- **IA** : prévision des ventes (baseline, Random Forest, XGBoost), réapprovisionnement, analyses RH / finance / marketing,
  orchestrateur, assistant conversationnel, commandes vocales avec confirmation.
- **Synchronisation** : le chiffre d'affaires = argent encaissé ; un retour ou une annulation met à jour stock, finance,
  comptabilité et fidélité ; un employé en congé ne peut pas être pointé présent.

## Installation

Prérequis : PHP 8.2+, Composer, MySQL (XAMPP), Python 3.11+ (Anaconda conseillé).

### 1. Application Laravel

```bash
cd si-intelligent
composer install
cp .env.example .env
php artisan key:generate
# Renseigner DB_DATABASE=si_intelligent, DB_USERNAME, DB_PASSWORD, AI_SERVICE_API_KEY dans .env
php artisan migrate
php artisan db:seed --class="Database\Seeders\RolePermissionSeeder"
php artisan app:seed-demo        # données de démonstration
php artisan serve                # http://127.0.0.1:8000
php artisan serve --port=8002    # 2e serveur : appels internes de l'assistant IA (Windows)
```

Compte de démonstration : `test@test.com` / `password` (**à changer hors démonstration**).

### 2. Service Python

```bash
cd ai-service
conda create -n si-intelligent-ai python=3.11 -y
conda activate si-intelligent-ai
pip install -r requirements.txt
cp .env.example .env
# Renseigner dans .env : accès MySQL, AI_SERVICE_API_KEY (identique à Laravel),
# GEMINI_API_KEY, GEMINI_MODEL, LARAVEL_BASE_URL=http://localhost:8002
uvicorn app.main:app --reload --port 8001
```

## Sécurité

- Les fichiers `.env` ne sont **jamais** versionnés (clés API, mots de passe). Ne publiez jamais une clé dans un message, une issue ou un commit.
- Le LLM n'accède jamais directement à la base : il passe par l'API Laravel avec le jeton et les permissions de l'utilisateur.
- Les actions qui engagent de l'argent ou du stock demandées à la voix exigent une confirmation.

## État du projet

Projet académique : données de démonstration générées, pas de suite de tests automatisés à ce jour,
stock non ventilé par entrepôt (structure prête).
