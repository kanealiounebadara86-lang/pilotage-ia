# ai-service — Service Python de prévision des ventes (Phase 5)

Service FastAPI indépendant, qui se connecte en lecture à la même base MySQL
que Laravel pour entraîner des modèles de prévision des ventes et exposer
une API que Laravel consomme via `AiGatewayService`.

## Pipeline implémenté (cahier des charges, section 16)

```
MySQL (sale_items + sales)
  → Extraction (fetch_sales_history)
  → Nettoyage : agrégation journalière, jours sans vente = 0, écrêtage des valeurs aberrantes (IQR)
  → Feature engineering : jour de semaine, lags, moyennes mobiles 7/14 jours
  → Baseline OBLIGATOIRE : modèle naïf + moyenne mobile
  → Modèles ML (si ≥30 jours d'historique) : Random Forest + XGBoost
  → Évaluation : MAE, RMSE, WAPE — même protocole (holdout) pour tous les modèles
  → Sélection du meilleur modèle (MAE le plus bas, baseline incluse)
  → Prévision récursive sur l'horizon demandé, avec intervalle de confiance
  → Sauvegarde du modèle (models_store/) si un modèle ML a été retenu
```

Le service ne retient un modèle ML que s'il bat réellement les baselines —
c'est directement exploitable dans ton mémoire pour la partie expérimentale
(comparaison méthode classique vs IA, section 49 du cahier des charges).

## Installation avec Anaconda (recommandé sous Windows)

### 1. Ouvrir Anaconda Prompt

Cherche "Anaconda Prompt" dans le menu Démarrer (pas le terminal Windows normal).

### 2. Créer un environnement dédié

```bash
conda create -n si-intelligent-ai python=3.11
conda activate si-intelligent-ai
```

Un environnement dédié évite les conflits de versions avec d'autres projets Python.

### 3. Se placer dans le dossier du service et installer les dépendances

```bash
cd C:\xampp\htdocs\ai-service
pip install -r requirements.txt
```

### 4. Configurer la connexion à la base de données

```bash
copy .env.example .env
```

Ouvre `.env` dans VS Code et vérifie que `DB_DATABASE`, `DB_USERNAME`,
`DB_PASSWORD` correspondent à ta configuration MySQL/XAMPP (les mêmes valeurs
que dans le `.env` de `si-intelligent`).

Choisis aussi une clé pour `AI_SERVICE_API_KEY` (n'importe quelle chaîne
suffisamment longue, ex: `change-me-to-something-long-and-random`), et
**reporte cette même valeur** dans le `.env` de ton projet Laravel
(`AI_SERVICE_API_KEY=...`) — les deux doivent être identiques, sinon Laravel
ne pourra pas s'authentifier auprès du service Python.

### 5. Lancer le service

```bash
uvicorn app.main:app --reload --port 8001
```

Tu dois voir `Uvicorn running on http://127.0.0.1:8001`. Laisse cette fenêtre
ouverte (comme pour `php artisan serve`) — c'est un **troisième** terminal à
garder actif en plus du serveur Laravel.

### 6. Vérifier que ça fonctionne

Ouvre dans ton navigateur : **http://127.0.0.1:8001/health** — tu dois voir
`{"status":"ok","service":"ai-service"}`.

Tu peux aussi explorer l'API interactive (générée automatiquement par
FastAPI) : **http://127.0.0.1:8001/docs**

## Configuration côté Laravel

Dans le `.env` de `si-intelligent`, vérifie que ces lignes sont présentes
(elles étaient déjà prévues dans le `.env.example` depuis la Phase 1) :

```
AI_SERVICE_BASE_URL=http://localhost:8001
AI_SERVICE_API_KEY=change-me-to-something-long-and-random
```

Il faut aussi **fusionner** (pas écraser) le fichier `config/services.php`
fourni avec celui déjà présent dans ton projet Laravel — ajoute juste la clé
`'ai' => [...]` dans le tableau existant, à côté de `'mailgun'`, `'postmark'`,
etc. Écraser le fichier casserait la config mail par défaut de Laravel.

## Trois terminaux à faire tourner en parallèle

| Terminal | Commande | Rôle |
|---|---|---|
| A | `php artisan serve` | Backend + interface Laravel |
| B | *(libre pour tes commandes)* | tests, migrations, etc. |
| C | `uvicorn app.main:app --reload --port 8001` | Service IA Python |

## Tester le pipeline complet

Une fois les trois terminaux actifs, va sur **http://127.0.0.1:8000/forecast**
dans ton navigateur, choisis ton produit "Jus d'orange 1L", et lance une
prévision.

⚠️ **Il faut assez d'historique pour que ce soit intéressant.** Avec 4-5
ventes ponctuelles, le service utilisera surtout les baselines (pas assez de
jours pour entraîner un modèle ML fiable — seuil fixé à 30 jours d'historique
dans `pipeline.py`). Pour un vrai test avec Random Forest/XGBoost, il faudra
soit attendre d'avoir plus de données réelles, soit générer un jeu de données
de démonstration avec plusieurs mois de ventes historiques (module 33 du
cahier des charges — pas encore construit, à faire si tu veux tester le ML
plus sérieusement dès maintenant).

## Ce qui n'est PAS encore fait

- Génération de données de démonstration (jeu de données synthétique réaliste)
- Endpoints `/api/ai/replenishment`, `/api/ai/explain` (optimisation, XAI — Phases 6-7)
- Assistant LLM (Phase 8)
- Ré-entraînement périodique automatique (tâche planifiée)
- Tests automatisés du service Python
