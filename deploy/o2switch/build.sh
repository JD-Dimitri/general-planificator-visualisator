#!/usr/bin/env bash
#
# Prépare l'archive à téléverser sur o2switch.
#
# Elle contient deux dossiers, à déposer à deux endroits différents :
#   gpv/         → dans le répertoire personnel, à côté de public_html
#   public_html/ → le contenu va dans la racine web du domaine
#
# Le code de l'application reste donc hors de la racine web : ni .env.local,
# ni les clés JWT, ni var/ ne sont servis par Apache.

set -euo pipefail

racine="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
sortie="$racine/build/o2switch"
archive="$racine/build/gpv-o2switch.zip"

echo "→ Construction du front"
cd "$racine/frontend"
npm ci --silent
npm run build

echo "→ Dépendances PHP de production"
cd "$racine/backend"
composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --quiet

echo "→ Assemblage"
rm -rf "$sortie" "$archive"
mkdir -p "$sortie/gpv/backend" "$sortie/public_html"

# Le backend, sans ce qui ne sert qu'au développement.
cd "$racine/backend"
for chemin in bin config migrations public src vendor composer.json composer.lock .env; do
  cp -r "$chemin" "$sortie/gpv/backend/"
done
mkdir -p "$sortie/gpv/backend/var" "$sortie/gpv/backend/config/jwt"

# La racine web : le build React, puis le contrôleur frontal et les règles Apache.
cp -r "$racine/frontend/dist/." "$sortie/public_html/"
cp "$racine/deploy/o2switch/index.php" "$sortie/public_html/index.php"
cp "$racine/deploy/o2switch/htaccess" "$sortie/public_html/.htaccess"
cp "$racine/deploy/o2switch/env.local.exemple" "$sortie/gpv/backend/.env.local.exemple"

# Symfony sert les fichiers du front, pas les siens : public/ du backend ne
# part pas dans la racine web.
rm -rf "$sortie/gpv/backend/public"

cd "$sortie"
zip -rq "$archive" gpv public_html -x '*.DS_Store'

echo
echo "Archive prête : $archive"
echo
echo "Sur le serveur, une fois les deux dossiers en place :"
echo "  1. cp ~/gpv/backend/.env.local.exemple ~/gpv/backend/.env.local  puis remplis-le"
echo "  2. cd ~/gpv/backend && php bin/console lexik:jwt:generate-keypair"
echo "  3. php bin/console doctrine:migrations:migrate --no-interaction"
echo "  4. php bin/console cache:clear && php bin/console cache:warmup"
echo "  5. Ouvre https://ton-domaine/api/health — il doit répondre {\"status\":\"ok\"}"
