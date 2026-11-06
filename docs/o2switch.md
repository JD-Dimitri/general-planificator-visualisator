# Mettre GPV en ligne sur o2switch

o2switch est un hébergement mutualisé sous cPanel. Cela veut dire **MySQL /
MariaDB** plutôt que PostgreSQL, Apache avec des fichiers `.htaccess`, et PHP
choisi par domaine. GPV fonctionne sur les deux moteurs ; la migration détecte
celui qui est en face.

Compte une demi-heure la première fois.

---

## 1. Préparer l'archive sur ton poste

```bash
./deploy/o2switch/build.sh
```

Le script construit le front, installe les dépendances PHP sans celles de
développement, et produit `build/gpv-o2switch.zip` contenant deux dossiers :

| Dossier | Où il va | Ce qu'il contient |
| --- | --- | --- |
| `gpv/` | dans ton répertoire personnel, **à côté** de `public_html` | tout le code de l'application |
| `public_html/` | le contenu va dans la racine web du domaine | le front compilé, `index.php`, `.htaccess` |

Le code reste **hors de la racine web**. Ni `.env.local`, ni les clés JWT, ni
le dossier `var` ne sont accessibles depuis internet.

---

## 2. Régler PHP

cPanel › **Sélecteur de version PHP** : choisis **8.3** pour le domaine, puis
vérifie que ces extensions sont cochées :

`pdo_mysql` · `intl` · `mbstring` · `sodium` · `openssl` · `ctype` · `iconv` ·
`json` · `zip` · `opcache`

`sodium` est celle qui manque le plus souvent. Sans elle, le hachage des mots
de passe échoue au premier compte créé.

---

## 3. Créer la base

cPanel › **Bases de données MySQL** :

1. Crée la base `gpv`. cPanel la préfixe : elle s'appellera `moncpanel_gpv`.
2. Crée un utilisateur avec un mot de passe long.
3. Ajoute l'utilisateur à la base avec **tous les privilèges**.

Note les trois valeurs : elles vont dans `DATABASE_URL`.

---

## 4. Créer la boîte d'envoi

cPanel › **Comptes de messagerie** : crée `gpv@mondomaine.fr`.

Ce n'est pas facultatif. GPV exige un code à usage unique à chaque connexion ;
sans envoi qui fonctionne, **personne ne peut se connecter**, toi compris.

---

## 5. Déposer les fichiers

Par FTP, par le gestionnaire de fichiers de cPanel, ou en SSH :

```bash
scp build/gpv-o2switch.zip moncpanel@mondomaine.fr:~
ssh moncpanel@mondomaine.fr
unzip gpv-o2switch.zip
rsync -a public_html/ ~/public_html/ && rm -rf public_html
```

Ton répertoire personnel ressemble alors à ceci :

```
~/
├── gpv/
│   └── backend/        (bin, config, migrations, src, vendor, .env)
└── public_html/
    ├── .htaccess
    ├── index.php
    ├── index.html
    └── assets/
```

---

## 6. Configurer et installer

En SSH, depuis `~/gpv/backend` :

```bash
cp .env.local.exemple .env.local
nano .env.local          # remplis les six valeurs marquées REMPLACE_MOI

php bin/console lexik:jwt:generate-keypair
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console cache:clear
php bin/console cache:warmup
chmod -R 755 var
```

`APP_SECRET` se génère ainsi :

```bash
php -r "echo bin2hex(random_bytes(16)), PHP_EOL;"
```

---

## 7. Vérifier

```
https://mondomaine.fr/api/health   →   {"status":"ok","database":"up"}
https://mondomaine.fr/             →   l'écran de connexion de GPV
```

Crée ton compte, puis relève le code dans ta boîte `gpv@mondomaine.fr`.

Active enfin le certificat : cPanel › **SSL/TLS Status** › *Run AutoSSL*. Tant
qu'il n'est pas émis, commente les trois lignes de redirection HTTPS en haut
de `.htaccess`, sinon le site boucle.

---

## 8. La tâche de nuit

cPanel › **Tâches Cron**, une fois par jour :

```
0 4 * * *   cd ~/gpv/backend && /usr/local/bin/ea-php83 bin/console app:purge-expired
```

Elle supprime les codes et les liens expirés. Sans elle, les tables grossissent
indéfiniment.

---

## Mettre à jour plus tard

```bash
./deploy/o2switch/build.sh
scp build/gpv-o2switch.zip moncpanel@mondomaine.fr:~
```

Puis sur le serveur :

```bash
unzip -o gpv-o2switch.zip
rsync -a --delete public_html/ ~/public_html/ && rm -rf public_html
cd ~/gpv/backend
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console cache:clear && php bin/console cache:warmup
```

`.env.local` et `config/jwt/` ne sont jamais dans l'archive : ils survivent aux
mises à jour.

---

## Sans accès SSH

Tout se fait depuis cPanel, en un peu plus long :

- **Dépôt** : Gestionnaire de fichiers › Téléverser, puis Extraire.
- **Migrations** : cPanel › Terminal, s'il est proposé. Sinon, exporte le
  schéma depuis ton poste et importe-le dans phpMyAdmin :
  ```bash
  cd backend
  php bin/console doctrine:schema:create --dump-sql > schema.sql
  ```
- **Clés JWT** : génère-les sur ton poste et téléverse `config/jwt/`.
  ```bash
  php bin/console lexik:jwt:generate-keypair
  ```
- **Cache** : supprime `~/gpv/backend/var/cache` à la main après chaque mise à
  jour. Symfony le reconstruit à la première requête.

---

## Si ça ne marche pas

| Symptôme | Cause la plus fréquente |
| --- | --- |
| Page blanche, erreur 500 | `var/` non inscriptible, ou une extension PHP manquante. Regarde `~/gpv/backend/var/log/prod.log`. |
| `/api/health` répond du HTML | `.htaccess` absent de `public_html`, ou `mod_rewrite` désactivé. |
| `database: down` | `DATABASE_URL` : nom et utilisateur doivent porter le préfixe cPanel. |
| Le code n'arrive jamais | `MAILER_DSN`. Teste l'envoi depuis le webmail, et regarde dans les indésirables. |
| Connecté puis déconnecté aussitôt | `APP_URL` et le domaine réel ne correspondent pas, le cookie est posé sur un autre hôte. |
| 404 en rechargeant `/domaines/...` | La dernière règle de `.htaccess` manque. |
| « Unauthorized » avec un jeton valide | La règle `HTTP:Authorization` manque : Apache en CGI mange cet en-tête. |

Après chaque changement de `.env.local` : `php bin/console cache:clear`.
