# Déployer Pilotage.IA sur un serveur (par version)

Résultat : l'application en ligne en **HTTPS**, accessible depuis n'importe quel téléphone (micro et installation
sur l'écran d'accueil inclus), avec **une mise à jour = une commande**, et retour en arrière possible.

## Ce qu'il vous faut

1. **Un serveur (VPS)** Linux Ubuntu 22.04 ou 24.04, **2 Go de RAM minimum** (les bibliothèques de prévision sont gourmandes).
   Compter environ 4 à 7 € par mois (Hetzner, OVH, Contabo…).
2. **Un nom de domaine** qui pointe vers l'adresse IP du serveur. Gratuit : un sous-domaine DuckDNS (duckdns.org),
   par exemple `pilotage-alioune.duckdns.org` → entrer l'IP du serveur.
3. Votre dépôt GitHub `pilotage-ia` avec au moins une version publiée (ex. `v1.0.0`).

## 1. Préparer le serveur (une seule fois)

Se connecter en SSH (`ssh root@IP-DU-SERVEUR`), puis :

```bash
# Pare-feu : SSH, web uniquement (MySQL n'est jamais exposé)
ufw allow 22 && ufw allow 80 && ufw allow 443 && ufw --force enable

# Docker
curl -fsSL https://get.docker.com | sh
```

## 2. Récupérer le projet depuis GitHub

Le dépôt étant privé, GitHub demande un **jeton d'accès** à la place du mot de passe :
GitHub → Settings → Developer settings → Personal access tokens → **Fine-grained tokens** → Generate :
dépôt `pilotage-ia` uniquement, permission **Contents : Read-only**.

```bash
git clone https://github.com/kanealiounebadara86-lang/pilotage-ia.git
cd pilotage-ia
# Nom d'utilisateur : votre pseudo GitHub — Mot de passe : le jeton
```

## 3. Configurer les secrets

```bash
cp .env.example .env
echo "APP_KEY=base64:$(openssl rand -base64 32)"
echo "DB_PASSWORD=$(openssl rand -hex 16)"
echo "DB_ROOT_PASSWORD=$(openssl rand -hex 16)"
echo "AI_SERVICE_API_KEY=$(openssl rand -hex 24)"
nano .env
```

Collez les valeurs générées dans `.env`, renseignez `DOMAIN` et votre `GEMINI_API_KEY`
(une **nouvelle** clé, pas celle partagée dans une conversation). Enregistrer : Ctrl+O, Entrée, Ctrl+X.
Le fichier `.env` n'est jamais envoyé sur GitHub.

## 4. Déployer une version

```bash
chmod +x deploy.sh
./deploy.sh v1.0.0
```

La première fois, la construction prend 5 à 10 minutes. L'application crée elle-même ses tables.

## 5. Créer l'entreprise et le premier administrateur

```bash
docker compose exec app php artisan app:create-admin
```

Répondez aux questions (nom de l'entreprise, nom, e-mail, mot de passe de 10 caractères minimum).
Ouvrez ensuite `https://votre-domaine` et connectez-vous.

> N'utilisez **pas** `app:seed-demo` sur un serveur public : il crée le compte `test@test.com / password`.

## 6. Installer sur le téléphone

Ouvrir `https://votre-domaine` dans Chrome (Android) ou Safari (iPhone), se connecter, puis :
Android → menu ⋮ → **Installer l'application** ; iPhone → Partager → **Sur l'écran d'accueil**.
Le micro de l'assistant vocal fonctionne car l'adresse est en HTTPS.

## Mettre à jour / revenir en arrière

Sur votre PC : enregistrer les modifications, puis créer la version suivante :

```bash
git add . && git commit -m "Description" && git push
git tag -a v1.1.0 -m "Version 1.1.0" && git push origin v1.1.0
```

Sur le serveur :

```bash
cd pilotage-ia
docker compose exec db sh -c 'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" si_intelligent' > sauvegarde-$(date +%F).sql
./deploy.sh v1.1.0        # mise à jour
./deploy.sh v1.0.0        # retour à l'ancienne version du code
```

⚠ Un retour en arrière remet l'ancien **code**, pas les anciennes **données** : gardez la sauvegarde faite avant la mise à jour.

## Dépannage

```bash
docker compose ps                 # état des 4 services
docker compose logs -f app        # journal de l'application
docker compose logs -f ai         # journal du service IA
docker compose logs -f proxy      # certificat HTTPS
```

- Page inaccessible : vérifier que le domaine pointe bien vers l'IP du serveur et que les ports 80/443 sont ouverts.
- Assistant qui ne répond pas : vérifier `GEMINI_API_KEY` dans `.env`, puis `docker compose up -d`.
