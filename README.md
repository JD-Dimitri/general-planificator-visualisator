# GPV — General Planificator Visualisator

Tout ce qu'il y a à faire, dans tous les domaines de la vie, sur un seul écran
ouvert en permanence.

Ce dépôt est le **squelette** de la version produit : API Symfony, front React,
authentification par cookie et par jeton, et une tranche complète de bout en bout
(domaines et sous-domaines imbriqués) qui sert de modèle aux suivantes.

Le dossier `standalone/` contient l'autre version : un seul fichier HTML, sans
compte ni serveur, qui fonctionne hors ligne. C'est celle qui tourne aujourd'hui
sur le grand écran.

---

## Démarrer

Il faut PHP 8.2 ou plus, Composer, Node 20 ou plus, et PostgreSQL 16.

```bash
make install     # dépendances PHP et JavaScript
make keys        # paire de clés JWT (une seule fois)
make db          # base + compte de démonstration
make dev-api     # API sur http://127.0.0.1:8000
make dev-web     # front sur http://127.0.0.1:5173
```

Avec Docker, `docker compose up` lance la base, l'API et le front d'un coup ;
il reste à faire `make keys` et `make db` la première fois.

Compte de démonstration : `demo@gpv.local` / `motdepassededemo`.

Le front appelle `/api`, que Vite renvoie vers le port 8000. Front et API sont
donc sur la même origine en développement comme en production : le cookie de
session fonctionne sans configuration CORS particulière.

---

## Ce qui est écrit

**Modèle de données complet** — `backend/src/Entity/` contient les dix entités de
GPV : comptes, domaines, sous-domaines imbriqués, éléments et leurs étapes, accès
directs, vacations, boîte de dépôt, séances de focus, jetons de rafraîchissement.
La migration initiale les crée toutes.

**Authentification en deux temps.** GPV ne s'ouvre pas sans compte, et le mot de
passe seul n'ouvre jamais de session : il faut aussi un code à six chiffres,
envoyé par e-mail ou par SMS, neuf à chaque connexion.

| Étape | Route | Ce qu'elle fait |
| --- | --- | --- |
| Créer un compte | `POST /api/auth/register` | Compte non validé, code envoyé |
| Valider | `POST /api/auth/verify` | Consomme le code, ouvre la session |
| Connexion, 1 | `POST /api/auth/login` | Vérifie le mot de passe, envoie un code |
| Connexion, 2 | `POST /api/auth/login/verify` | Consomme le code, ouvre la session |
| Renvoyer | `POST /api/auth/code/resend` | Nouveau code, l'ancien cesse de valoir |
| Mobile, 1 et 2 | `POST /api/auth/token`, `/token/verify` | Même parcours, rend un JWT |
| Renouvellement | `POST /api/auth/refresh` | Rotation : l'ancien jeton est révoqué |
| Oubli, demande | `POST /api/auth/password/forgot` | Envoie un lien, réponse identique pour toute adresse |
| Oubli, contrôle | `GET /api/auth/password/reset/{token}` | Dit si le lien est encore valable |
| Oubli, nouveau | `POST /api/auth/password/reset` | Change le mot de passe et connecte |

Ce qui tient cette partie debout :

- le pare-feu n'a **pas** de `json_login`. Il ne doit exister aucune route où
  adresse + mot de passe suffisent, sinon le code se contournerait. La session
  est posée à la main, après la deuxième étape seulement ;
- codes et jetons ne sont jamais stockés en clair : HMAC-SHA256 pour les codes,
  SHA-256 pour les liens et les jetons de rafraîchissement ;
- un code vit dix minutes, supporte cinq essais, ne sert qu'une fois, et un
  nouveau code annule le précédent ;
- un lien de réinitialisation vit une heure et ne sert qu'une fois ; changer le
  mot de passe révoque les jetons et déconnecte les autres appareils ;
- « mot de passe oublié » répond la même chose pour une adresse connue ou non,
  et « mot de passe incorrect » ne se distingue pas de « compte inconnu » :
  ces formulaires ne doivent pas servir à savoir qui est inscrit ;
- huit codes par heure et par compte, cinq liens par heure, dix tentatives par
  tranche de cinq minutes et par adresse IP.

Le SMS demande un fournisseur payant (Twilio, OVH, Vonage) via `TEXTER_DSN`.
Sans transport configuré, ou si l'envoi échoue, GPV bascule sur l'e-mail plutôt
que de laisser quelqu'un attendre un code qui n'arrivera jamais.

En développement, `EXPOSE_DEV_CODES=1` fait renvoyer le code et le lien par
l'API : le parcours s'essaie sans serveur de mail. À laisser à `0` ailleurs.

**API complète**

| Ressource | Route | Particularités |
| --- | --- | --- |
| Domaines | `/api/domains` | `DELETE ?moveTo=` déplace le contenu au lieu de le perdre |
| Sous-domaines | `/api/sub-domains` | Imbrication jusqu'à six niveaux, `?domainId=` |
| Éléments | `/api/items` | Filtres `domainId`, `subDomainId`, `status`, `due`, `open` |
| Étapes | `/api/items/{id}/subtasks/{stepId}` | Coche une étape sans renvoyer l'élément |
| Accès directs | `/api/access-links` | Adresses limitées à http(s) |
| Vacations | `/api/shifts` | `?from=&to=`, les nuits débordent sur le lendemain |
| Boîte de dépôt | `/api/inbox` | Import en lot, `POST /{id}/file` range la ligne |
| Focus | `/api/focus-sessions` | `/stats?days=` agrège par jour et par domaine |
| Instantané | `/api/snapshot` | Tout l'écran en une requête |

Les règles qui tiennent les données droites :

- un sous-domaine s'imbrique sans limite pratique, jusqu'à six niveaux ;
- il porte toujours le domaine de son parent, même si la requête dit autre chose ;
- il ne peut pas devenir son propre descendant ;
- supprimer un domaine propose de déplacer son contenu (`?moveTo=`) plutôt que
  de le perdre ; supprimer un sous-domaine fait remonter le sien d'un niveau ;
- un élément rangé dans un sous-domaine prend le domaine de celui-ci ;
- filtrer sur « Parents » rapporte aussi ce qui est rangé dans Maman et Papa ;
- un élément récurrent terminé engendre son occurrence suivante, une seule fois,
  même s'il est rouvert puis reterminé ;
- une ligne fautive au milieu d'un import n'en laisse pas la moitié enregistrée.

**Front React** — le parcours d'authentification en entier (connexion,
inscription, saisie du code avec compte à rebours et renvoi, mot de passe
oublié, écran de réinitialisation), l'œil qui montre ou masque les mots de
passe, le tableau des domaines et le détail d'un domaine avec son arbre de
sous-domaines. Les jetons du thème GPV sont repris tels quels, en clair et en
sombre.

**Tests** — 74 tests couvrent l'authentification en deux temps, la
réinitialisation du mot de passe, le cloisonnement entre comptes, la validation,
les règles de l'arbre, la récurrence et l'instantané.

```bash
make test
```

---

## Ce qu'il reste à écrire

L'API est complète. Ce qui manque est côté front et côté intégrations.

- [ ] Porter la disposition complète de la version autonome : bandeau avec
      compteurs, trois colonnes, planning, Kanban, liste, calendrier, boîte de
      dépôt, bloc-notes. Le front ne couvre pour l'instant que l'authentification,
      les domaines et les sous-domaines ; `GET /api/snapshot` donne tout le
      reste en une requête.
- [ ] Import de fichiers (Word, PDF, Excel) : extraction dans le navigateur avec
      mammoth, pdf.js et SheetJS, puis envoi des lignes à `POST /api/inbox`
- [ ] Mode Focus et mode Ambiance, portés depuis la version autonome ; les
      séances remontent à `POST /api/focus-sessions`
- [ ] Mails et agenda : OAuth Microsoft et Google côté Symfony
- [ ] Tri automatique par IA, réservé à l'offre connectée
- [ ] Application de bureau (Tauri) pour les panneaux Doctolib, Gmail et Drive

Pour ajouter une ressource, le modèle est `DomainController` : une DTO dans
`src/Dto/`, un contrôleur qui étend `AbstractApiController`, un test dans
`tests/Api/`. Compte une soixantaine de lignes.

---

## Choix d'architecture

**Identifiants UUID v7.** Triés dans le temps comme un entier auto-incrémenté,
mais générés par le client. Une tâche créée hors ligne part avec son identifiant
définitif et se synchronise sans collision ni renumérotation. Le client peut
imposer l'identifiant à la création ; rejouer la même requête ne crée pas de
doublon.

**Contrôleurs explicites plutôt qu'API Platform.** Le code montre ce qu'il fait,
sans couche de configuration à apprendre. API Platform apporterait pagination,
filtres et OpenAPI gratuitement : c'est le bon échange si l'API grossit beaucoup.

**DTO d'entrée séparées des entités.** Le client n'écrit que ce qui est déclaré
dans `src/Dto/` : pas d'affectation en masse, et les messages de validation sont
en français, prêts à afficher. Pour un `PATCH`, la DTO est d'abord remplie depuis
l'entité puis le JSON reçu écrit par-dessus, de sorte qu'un champ absent garde sa
valeur et qu'un champ à `null` est bien effacé.

**Cloisonnement à deux barrières.** Les requêtes filtrent par propriétaire, et
`OwnerVoter` revérifie chaque objet. La seconde barrière tient si une requête
oublie un jour son filtre. Tout identifiant reçu du client passe par
`OwnedLocator` : inconnu ou appartenant à quelqu'un d'autre, il donne le même
résultat, de sorte que l'API ne révèle jamais l'existence des données des autres.

**Récurrence ancrée et rattrapée.** « Le 31 de chaque mois » ramené au 28 février
revient au 31 en mars : le quantième de départ est gardé sur l'élément et
transmis à chaque occurrence. Une tâche oubliée deux ans repart aujourd'hui, et
le rattrapage se calcule d'un coup plutôt qu'en avançant jour après jour.

**Jeton d'accès en mémoire côté client.** Jamais dans `localStorage`, qu'un
script injecté pourrait lire, à la différence d'un cookie HttpOnly.

---

## Déploiement

`.platform.app.yaml` et `.platform/` visent Platform.sh. Le principe vaut pour
Scalingo et Clever Cloud : construire le front, servir `backend/public` sur
`/api` et `frontend/dist` sur le reste, lire `DATABASE_URL` dans l'environnement.

À régler avant la première mise en ligne :

```
APP_ENV=prod
APP_SECRET=<32 octets aléatoires>
DATABASE_URL=<fourni par l'hébergeur>
JWT_PASSPHRASE=<phrase secrète>
CORS_ALLOW_ORIGIN=^https://gpv\.exemple\.fr$
MAILER_DSN=<SMTP ou DSN de l'hébergeur>
MAILER_SENDER="GPV <bonjour@gpv.exemple.fr>"
APP_URL=https://gpv.exemple.fr
EXPOSE_DEV_CODES=0
```

`APP_SECRET` sert aussi à calculer l'empreinte des codes : le changer invalide
tous les codes en circulation, ce qui est sans gravité, mais à savoir.
`EXPOSE_DEV_CODES` doit rester à `0` : à `1`, l'API renvoie les codes en clair.
Sans `MAILER_DSN` qui fonctionne, personne ne peut se connecter — c'est le
premier réglage à vérifier après une mise en ligne.

Les clés JWT ne vont jamais dans git. Génère-les à la main sur l'hébergeur, ou
pose `JWT_SECRET_KEY` et `JWT_PUBLIC_KEY` en variables d'environnement.

Toute URL qui n'est pas `/api` rend `index.html` : sans cette règle, recharger la
page sur `/domaines/<id>` renverrait une erreur 404.

---

## Offres

`User::$plan` porte `free` ou `connected` et verrouille les fonctions connectées
décrites dans le cahier des charges. Le découpage est à valider avant d'écrire la
facturation.

| | Gratuit | Connectée |
| --- | --- | --- |
| Domaines, sous-domaines, éléments, notes | oui | oui |
| Vues liste, Kanban, tableau, calendrier | oui | oui |
| Synchronisation multi-appareils | oui | oui |
| Import Word, PDF, Excel | texte seulement | oui |
| Tri automatique et lecture de photos par IA | non | oui |
| Mails et agenda Outlook, Gmail | non | oui |

---

## Notes

La migration initiale est écrite à la main pour PostgreSQL et n'a pas été
exécutée ici, faute d'accès à Packagist dans l'environnement de génération. Si
elle bute sur quoi que ce soit, régénère-la :

```bash
rm backend/migrations/Version20261106120000.php
cd backend && php bin/console doctrine:migrations:diff
```

Les tests utilisent SQLite, créé à partir des entités : ils ne dépendent pas de
cette migration.
