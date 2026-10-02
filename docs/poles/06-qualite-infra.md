# Pôle 6 — Qualité, packaging et infrastructure

**Votre responsabilité :** prouver que la version peut être utilisée, installée et reprise par une autre personne. Les SLAM peuvent assurer QA/packaging ; le responsable d’infrastructure ou les SISR prennent la cible réelle.

## Deux parcours complémentaires

**QA :** préparer la matrice avant le code, créer les données fictives, exécuter les tests croisés, ouvrir les anomalies, vérifier les corrections puis remplir le rapport avec preuves. Tester les réponses négatives : sans compte, token absent, date invalide, doublon, erreur réseau. Un test n’est pas réussi parce qu’un développeur affirme que le code existe.

**Infra :** choisir cible et domaine tôt, comprendre les trois conteneurs et ports, installer HTTPS, préparer secrets et SMTP, tester santé et persistance, sauvegarder et restaurer sur pile isolée, générer le QR statique, produire le paquet et transmettre. Lisez `09-deploiement.md` avant de changer les ports.

## Missions et fichiers

- QA-01/02 : `livrables/tests/RAPPORT.md`, données fictives, tests ajoutés aux suites.
- INF-01/02 : cible, architecture, configuration type, compte CLI et vérification extérieure.
- INF-03 : `scripts/backup.sh`/`restore.sh`, preuve de restauration et procédure de reprise.
- INF-04 : support QR de l’URL finale et test sur téléphone hors réseau local.
- REL-01 : version, archive/checksum, `livrables/deploiement/TRANSMISSION.md`, checklist et PV.

Ne changez pas la production du salon pendant une recette de restauration. Les noms de projet Compose distincts isolent les volumes ; les ports doivent aussi être distincts. Vérifiez le fichier `.env.restore` et le projet cible à chaque commande.

## Vous demandez et transmettez

À pilotage : périmètre, critères et heure de bascule. À développeurs : commit à tester, commande et données fictives. À directrice/exploitant : notice et données nécessaires, relais SMTP, contacts. À équipe salon : URL, usage, erreurs et secours. Ne mettez pas un mot de passe dans la fiche de transmission versionnée.

## Terminé lorsque

Rapport avec observations réelles, anomalies bloquantes corrigées, installation propre reproductible, HTTPS et cookie Secure observés, données conservées après redémarrage, restauration vérifiée, QR imprimé scanné en 4G/5G, manuel utilisable par une personne extérieure. La fiche individuelle SISR précise ce qui a réellement été déployé par l’étudiant ; celle QA précise les tests conçus et exécutés.
