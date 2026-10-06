#!/usr/bin/env bash
# Déploie une VERSION précise du projet (étiquette Git).
#   ./deploy.sh v1.0.0     -> déploie la version 1.0.0
#   ./deploy.sh            -> déploie la dernière version publiée
#   ./deploy.sh v1.0.0     -> aussi pour REVENIR en arrière
# Pensez à sauvegarder la base avant une mise à jour (voir DEPLOIEMENT.md).
set -euo pipefail
cd "$(dirname "$0")"

[ -f .env ] || { echo "Fichier .env manquant : copiez .env.example en .env et remplissez-le."; exit 1; }

git fetch --tags --quiet
VERSION="${1:-$(git tag --sort=-v:refname | head -n1)}"
[ -n "$VERSION" ] || { echo "Aucune version (étiquette) trouvée dans le dépôt."; exit 1; }

echo ">>> Déploiement de la version $VERSION"
git checkout --quiet "$VERSION"
docker compose up -d --build
docker compose ps
echo "$VERSION" > .deployed-version
echo ">>> Version $VERSION en ligne."
