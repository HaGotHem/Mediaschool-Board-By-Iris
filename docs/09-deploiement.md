# 09 — Packager et transmettre un service facile à déployer

## Partage des responsabilités

Les SLAM livrent le code, les locks, les migrations, la configuration type, les tests et le guide. Les SISR ou le responsable d’infrastructure choisissent la cible, le domaine, le reverse proxy HTTPS, le réseau, les secrets, le relais SMTP et la sauvegarde. La directrice valide l’usage et l’information des visiteurs. Le QR code attend l’URL finale.

La pile contient `web` (Nginx + assets compilés), `api` (PHP-FPM + vendor Composer) et `db` (PostgreSQL). Aucun Node, PHP ou Composer local n’est nécessaire à l’exploitant s’il utilise Docker. Les volumes conservent données et sessions. Les migrations sont appliquées au démarrage de l’API ; une erreur empêche une fausse mise en route.

## Deux modes de livraison

**A — Sources versionnées.** Cible avec accès réseau aux registres : clone du dépôt, checkout du tag, `.env` local, build avec Compose. Les locks reproduisent les bibliothèques. L’exploitant peut reconstruire sans recevoir `node_modules` ni `vendor`.

**B — Images construites et exportées.** Cible sans accès aux dépendances ou salon à installer rapidement : build sur une machine compatible, `docker save` des images API/web et de PostgreSQL, `docker load` sur cible, Compose avec noms d’images explicites. Ce mode exige architecture compatible (`linux/amd64` ou `linux/arm64`) et un Compose de livraison correspondant ; ne transférez pas aveuglément des images Apple Silicon à un VPS amd64.

La voie A est documentée et fournit le paquet de base. La voie B est une mission d’infrastructure si nécessaire. Le paquet contient guide, sources, locks, migrations, exemples sans secrets et tests ; **pas** de `.env`, sauvegarde de contacts ou export réel.

## Construire le paquet A à partir d’un commit

Sur une machine avec Git, après intégration et tests :

```bash
git status --short
git rev-parse HEAD
# Après vérification, choisir un nom de version métier, ex. v1.0.0-salon.
git tag -a v1.0.0-salon -m "Version salon validée"
git push origin v1.0.0-salon
bash scripts/package.sh v1.0.0-salon
```

Le script archive les fichiers **du ref Git fourni**, pas les fichiers non committés. Il crée un `.tar.gz` et un SHA-256 dans `release/`. Ces fichiers ne sont pas committés. Renseignez commit, tag, checksum et architecture dans `livrables/deploiement/TRANSMISSION.md`. La première version du présent dépôt est un starter et ne doit pas recevoir le tag de version opérationnelle du salon.

## Installation sur une cible Linux

L’exploitant prépare Docker Engine + Compose v2, espace disque, accès d’administration et sauvegarde extérieure à la machine. Exemple d’installation avec sources :

```bash
git clone https://github.com/AstrowareConception/Mediaschool-Board-by-Iris-Nice.git
cd Mediaschool-Board-by-Iris-Nice
git checkout v1.0.0-salon
cp .env.production.example .env
chmod 600 .env
```

Renseignez l’URL HTTPS définitive, deux secrets indépendants d’au moins 24 caractères pour DB_PASSWORD et 32 pour APP_SECRET, événement et paramètres SMTP. Exemple pour obtenir un secret, à copier directement dans `.env` sans l’afficher dans une capture ou un ticket : `openssl rand -hex 32`. Choisissez un identifiant et un mot de passe administrateur spécifiques. La notice doit être réelle, les routes terminées et la recette concluante.

```bash
docker compose up -d --build --wait
docker compose exec api php bin/create-admin.php equipe-salon
docker compose exec api composer test
docker compose exec api composer smoke
```

Le service web écoute `127.0.0.1:8080` par défaut : le reverse proxy **sur l’hôte** cible `http://127.0.0.1:8080`. Si le reverse proxy est lui-même dans un conteneur, son `127.0.0.1` ne désigne pas l’hôte. L’exploitant utilise alors un réseau Docker partagé et le nom `web`, ou une passerelle hôte configurée et un bind restreint adapté. Ne rendez pas le port de base public pour résoudre ce problème.

## Reverse proxy et HTTPS

Configurez le domaine et le certificat avec l’outil d’infrastructure existant. Redirigez HTTP vers HTTPS. Préservez les chemins `/api` et `/admin.html`, et évitez un cache partagé sur les réponses API. Le cookie `Secure` est activé par `APP_URL=https://...`, même si le trafic interne proxy→Nginx est HTTP. Vérifiez-le dans les outils du navigateur.

Les ports 5432 et 9000 restent internes. Gardez les limites de corps et de débit. L’IP du visiteur peut nécessiter la configuration `real_ip` pour un proxy connu ; ne faites jamais confiance à tous les proxies par `0.0.0.0/0`. Ajoutez HSTS au proxy HTTPS après vérification du domaine et du renouvellement des certificats.

## SMTP

Renseignez hôte, port, chiffrement (`tls` pour STARTTLS ou `smtps`), compte, secret, adresse émettrice et liste des adresses réceptrices autorisées. Ces valeurs viennent de l’établissement ; n’inventez pas des identifiants. Vérifiez l’autorisation du relais et, avec l’exploitant, l’identité d’expéditeur/SPF/DKIM si nécessaires.

L’application joint seulement un récapitulatif agrégé. Le serveur valide le destinataire malgré sa saisie dans l’écran. Le succès SMTP signifie acceptation par le relais, pas preuve de lecture ni garantie d’absence de classement en spam. Testez le parcours d’abord avec données fictives et un destinataire de test autorisé.

## Ouvrir la collecte et produire le QR code

Quand le PV et la checklist sont remplis, retirez les messages de starter, activez `REGISTRATIONS_OPEN=true` dans `.env` puis `docker compose up -d --force-recreate api`. Testez une fiche fictive bout en bout sur la cible avant le début du salon et retirez-la de façon contrôlée. Les totaux doivent commencer sur les données prévues.

Générez un QR **statique** depuis `https://votre-domaine/`, avec l’outil de l’exploitant. Il contient uniquement l’URL du formulaire public ; pas de compte, secret, nom ou token. Exportez PNG/SVG avec marge blanche, forte lisibilité et URL imprimée en dessous. Essayez le support imprimé sur au moins deux téléphones, en 4G/5G, hors du réseau du VPS. Un QR de `localhost` ne fonctionne pas chez un visiteur.

## Sauvegarde et restauration

```bash
bash scripts/backup.sh
```

Le script produit un dump custom PostgreSQL dans `backups/` avec droits limités. Copiez-le vers un espace protégé hors du serveur selon la politique définie. La fréquence doit correspondre à la perte acceptable pendant le salon : convenez d’un intervalle avec l’exploitant et prévoyez une sauvegarde avant ouverture, après le salon et avant mise à jour. Les dumps contiennent des données personnelles ; ils ne vont jamais dans Git.

**Restauration d’essai obligatoire** sur une pile isolée. Pour éviter que l’interpolation d’un port déjà défini ne reprenne 8080, utilisez un fichier dédié :

```bash
cp .env.example .env.restore
# Modifier APP_PORT=8081 et APP_URL=http://localhost:8081 dans .env.restore.
docker compose --env-file .env.restore -p board-restore up -d --build --wait
bash scripts/restore.sh .env.restore board-restore backups/NOM-DU-DUMP.dump
```

Ce script refuse le nom de projet production, arrête web/api de la pile d’essai, restaure puis redémarre. Comptez les fiches et comparez les référentiels. Ne restaurez pas sur la base réelle « pour tester ». Documentez ensuite la procédure de reprise de production, ses responsables, son temps et sa perte de données maximale ; cette reprise nécessite une décision opérationnelle.

## Mise à jour et rollback

Avant mise à jour, fermez temporairement la collecte, sauvegardez, notez le commit actif et l’état des migrations. Installez le ref validé et reconstruisez. Les migrations ajoutent les changements ; ne les réécrivez pas. Un retour au code précédent ne défait pas les migrations : vérifiez sa compatibilité, sinon utilisez une procédure de restauration planifiée. Ne proposez pas `down -v` comme rollback.

## Passage de relais

Complétez la fiche de transmission : URL, version/commit, commande de départ, compte créé sans mot de passe dans le document, contact technique, domaine/certificat, mode de sauvegarde, résultats de restauration, SMTP, notice et limitations reconnues. Donnez le manuel à l’équipe du salon et faites réaliser une connexion, une consultation et un export sans votre assistance.
