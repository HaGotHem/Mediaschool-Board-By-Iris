# Pôle 2 — Merise et PostgreSQL

**Votre responsabilité :** rendre les données compréhensibles, intègres et requêtables. Lisez d’abord `04-donnees-merise.md` et le contrat API.

## Missions et ordre

1. DATA-01 : produire le MCD avec entités, propriétés, associations et cardinalités. Expliquer les facultatifs et l’unique souhait de formation.
2. DATA-02 : traduire en MLD, compléter le dictionnaire et choisir la normalisation du doublon.
3. Faire relire votre modèle au pôle API. Confirmer noms, ID, NULL et horodatage ; les listes fournies ne suffisent pas à modéliser les fiches.
4. DATA-03 : écrire `003_registrations.sql`, FK, CHECK utiles, index unique et indexes de consultation. Utiliser une migration suivante pour toute évolution.
5. DATA-04 : fournir un jeu fictif distinct et des requêtes liées pour liste, détail et récapitulatif.
6. Vérifier avec QA les deux INSERT concurrents, les ID inexistants, l’absence de spécialité, les accents et la persistance.

## Votre zone de travail

`backend/database/migrations/003_...sql`, jeu de données de test séparé, `livrables/conception/`, tests de contraintes. `registration.sql.example` est incomplet et ne doit pas être livré comme modèle final. N’éditez pas `001`/`002` après application ; les ID des listes doivent rester stables.

## Exemple à comprendre

Le récapitulatif regroupe par **école + niveau**, pas par classe actuelle. Une personne de Terminale visant IRIS/BTS 1 compte dans IRIS/BTS 1. Le total d’une page de vingt fiches n’est pas le total du salon. Écrivez le GROUP BY et vérifiez sa somme sur le jeu fictif.

## Vous transmettez

À API : migration, colonnes et types, définition des contraintes, requêtes et cas limites. À back-office : libellés, ordre et exemples de synthèse. À infra : commande des migrations et présence du volume. À QA : scénarios d’intégrité et résultat attendu.

## Terminé lorsque

MCD source/export, MLD et dictionnaire sont cohérents avec la migration ; une base propre s’installe ; les contraintes ont des preuves ; un camarade sait exécuter et expliquer vos requêtes. Votre fiche individuelle contient une décision de cardinalité et une preuve de contrainte, pas seulement une capture du logiciel de modélisation.
