# 03 — Installer, comprendre et diagnostiquer le starter

## Le chemin le plus court : tout via Docker

Installez Git et Docker Desktop (moteur Linux, Compose v2). Sous Windows, démarrez Docker Desktop avant le terminal et utilisez PowerShell ou un terminal Git Bash. Les commandes `docker compose` sont identiques ; `cp` devient `Copy-Item` sous PowerShell. Évitez un dossier synchronisé par un outil cloud pour les volumes ou les dépendances.

```bash
git clone https://github.com/AstrowareConception/Mediaschool-Board-by-Iris-Nice.git
cd Mediaschool-Board-by-Iris-Nice
cp .env.example .env
docker compose version
docker compose up -d --build --wait
docker compose ps
docker compose exec api php bin/create-admin.php equipe
```

Le premier build télécharge des images et des dépendances. Les suivants utilisent le cache. Le fichier `.env` reste local ; ses valeurs de développement ne sont jamais acceptées en production. Le mot de passe du compte n’est pas celui de PostgreSQL. Aucun compte admin n’est créé par défaut.

Ouvrez `/`, puis `/admin.html`. Sans compte, vous pouvez voir l’écran de connexion mais pas les fiches. Au démarrage, l’API applique `001_core.sql` puis `002_references.sql`. Les quatre listes apparaissent dans le formulaire. **Le bouton public doit actuellement donner « inscriptions non ouvertes »** : il reste du travail métier. Après développement et recette, ouvrez avec `REGISTRATIONS_OPEN=true`, puis recréez le conteneur API.

## Vérifier une fois son poste

```bash
curl http://localhost:8080/api/health
curl http://localhost:8080/api/references
docker compose exec api composer test
docker compose exec api composer smoke
```

Résultats : santé `ok`, quatre listes non vides, tests du socle réussis. `smoke` appelle Slim directement et PostgreSQL : il n’est pas une recette navigateur ou un test du proxy Nginx. Ne confondez pas « starter valide » et « application métier terminée ».

## Comprendre une requête avant d’ajouter votre route

1. Le navigateur appelle `/api/references` depuis la même origine.
2. Nginx transmet la requête à PHP-FPM.
3. `public/index.php` charge l’autoloader et `src/app.php`.
4. Slim reconnaît la route, puis le code utilise `Database::connect()` et `Medoo::select()`.
5. PostgreSQL retourne les référentiels et `Json::send()` construit la réponse.
6. `frontend/src/form.js` crée les options avec `textContent`.

Inspectez ces six fichiers, puis ajoutez votre propre code dans un composant séparé. Dans Slim, un middleware se greffe avec `add()`. L’ordre compte : les derniers ajoutés s’exécutent en premier. Les routes `/api/admin` ont déjà `AuthMiddleware` et `CsrfMiddleware` ; gardez vos nouvelles routes dans ce groupe.

## Éditer efficacement

**PHP :** l’override facultatif permet de relire les fichiers sans rebuild :

```bash
docker compose -f compose.yaml -f compose.dev.yaml up -d --build --wait
```

Gardez ensuite les deux `-f` pour les commandes `up` de cette session. Les répertoires source sont montés, mais `vendor/` reste celui de l’image. Après ajout d’une dépendance Composer, mettez à jour le lock et reconstruisez l’image ; une copie de code seule ne suffit pas.

**Front :** option simple, sans Node local :

```bash
docker compose build web
docker compose up -d --no-deps web
```

Option confortable avec Node 22.12+ ou Node 24 :

```bash
cd frontend
npm ci
npm run dev
```

Ouvrez l’URL affichée par Vite, généralement `http://127.0.0.1:5173`. `/api` est proxifié vers la pile Docker sur 8080. Si vous changez `APP_PORT`, changez aussi le proxy Vite. Vite sert uniquement au développement ; en livraison Nginx sert les assets compilés, sans CDN et sans Node à exécuter.

## Composer et fichiers verrouillés

Le `composer.lock` et le `package-lock.json` sont versionnés. Utilisez `composer install` et `npm ci` pour reproduire les versions. Les dépendances PHP sont dans l’image API ; Tailwind et daisyUI sont intégrés par Vite. N’utilisez pas un script CDN de développement pour le salon.

Pour ajouter une bibliothèque PHP sur un poste sans Composer :

```bash
docker run --rm -v "${PWD}/backend:/app" -w /app composer:2 require nom/paquet
```

Sous PowerShell, le montage utilise également `${PWD}`. Le conteneur Composer seul ne possède pas forcément `pdo_pgsql` : pour une mise à jour nécessitant les extensions, préférez un terminal dans l’image API avec un montage de `backend` ou une installation locale correspondante. Ne contournez pas arbitrairement les exigences de plateforme.

## Commandes de tous les jours

| Besoin | Commande |
|---|---|
| Voir les conteneurs | `docker compose ps` |
| Voir les erreurs | `docker compose logs --tail=100 api web` |
| Entrer dans PHP | `docker compose exec api sh` |
| Appliquer les nouvelles migrations | `docker compose exec api php bin/migrate.php` |
| Entrer dans PostgreSQL | `docker compose exec db sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"'` |
| Mettre à jour des variables `.env` | `docker compose up -d --force-recreate api` |
| Arrêter sans perdre les données | `docker compose down` |
| Reprendre | `docker compose up -d --wait` |

## Si cela ne fonctionne pas

| Symptôme | Diagnostic utile | Correction |
|---|---|---|
| Docker daemon inaccessible | Docker Desktop est-il lancé ? | Démarrer le moteur Linux, réessayer `docker info` |
| Port déjà pris | Une autre application utilise 8080 | `APP_PORT=8081` et recréation de web ; adapter URL et proxy Vite |
| API unhealthy / `board-entrypoint: no such file` | Lire `docker compose logs --tail=100 api` ; un script CRLF Windows peut casser le shebang Linux | Récupérer le correctif, puis `docker compose up -d --build --force-recreate --wait` ; les données sont conservées |
| Base unhealthy | `docker compose logs db` | Vérifier le secret et l’état du volume ; ne pas supprimer les données réelles |
| Mot de passe DB modifié mais rejeté | Un volume existe déjà | Le secret d’un rôle existant ne change pas avec `.env` : l’exploitant le change dans PostgreSQL et aligne la configuration |
| API échoue au démarrage | `docker compose logs api` | Lire la migration ou le message de configuration ; ajouter une nouvelle migration au lieu d’altérer une ancienne |
| Formulaire sans listes | Réseau navigateur, `/api/references` | Vérifier API, seed et `EVENT_ID` ; ne pas coder les listes en double dans le JS |
| 401 admin | Session absente/expirée | Se reconnecter ; conserver `credentials: same-origin` |
| 403 CSRF | Token absent ou périmé | Recharger la session ; récupérer le nouveau token après login |
| 501 métier | Route volontairement incomplète | Prendre la tâche du backlog ; ce n’est pas un problème Docker |
| 503 inscriptions | Ouverture désactivée | Développer et tester avant d’activer `REGISTRATIONS_OPEN` |

`docker compose down -v` supprime les volumes. Cette commande n’appartient ni au déploiement ni au diagnostic de production.

## Kit déjà cloné : récupérer un correctif de démarrage

Depuis votre branche, intégrez la version actualisée du kit selon les règles du groupe. Sur `main` sans modifications locales :

```bash
git pull --ff-only
docker compose up -d --build --force-recreate --wait
docker compose ps
```

Sous Windows, `.gitattributes` impose les fins de ligne LF aux scripts et le Dockerfile normalise également l’entrypoint avant exécution. Cela protège les clones existants et les archives ZIP. Il n’est pas nécessaire de supprimer les volumes de données.

Si l’API échoue encore, `docker compose logs --tail=100 api` indique maintenant les phases configuration, migrations et PHP-FPM. `docker inspect mediaschool-board-api-1 --format '{{json .State.Health}}'` donne les dernières erreurs du healthcheck. La vérification `pg_isready` du conteneur DB prouve que PostgreSQL répond, pas que les identifiants configurés dans l’API sont corrects.
