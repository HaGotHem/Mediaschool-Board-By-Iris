# Pôle 4 — Backend Slim, Medoo et sécurité

**Votre responsabilité :** seuls des champs valides sont enregistrés, les routes privées le restent et le front reçoit des erreurs stables.

## Missions et ordre

1. Lire le flux `/references` et les classes Database/Json/middlewares. Exécuter les tests du starter avant modification.
2. API-01 : créer un validateur séparé. Contrôler objets/types, trim, longueurs Unicode, mail/téléphone, date stricte et ID existants. Retourner toutes les erreurs utiles sous les noms du contrat.
3. API-02 : intégrer l’INSERT Medoo après la migration, générer UUID côté serveur et horodater au serveur. Respecter l’ouverture. Intercepter 23505 spécifiquement pour les doublons.
4. API-03 : créer liste paginée et détail du salon actif avec paramètres liés. Garder les nouvelles routes dans le groupe admin.
5. API-04 : produire un service de synthèse partagé par JSON, CSV/PDF et mail. Le front ne doit pas recalculer le total.
6. SEC-01 : vérifier connexion, déconnexion, accès direct, CSRF et nouvelles actions. Ajouter les tests métier au-delà de `composer smoke`.

## Organisation du code

Le starter centralise les routes pour les rendre visibles. Dès qu’elles grandissent, créez `src/Registration/Validator.php`, `Repository.php`, contrôleurs/routes séparés ou une organisation équivalente simple. Ne créez pas une architecture à vingt couches pour cette journée. Validation, accès aux données et construction HTTP doivent pouvoir être testés séparément.

Exemple : le repository reçoit uniquement les valeurs validées ; il construit les colonnes lui-même. Pas de `$db->insert('registrations', $request->getParsedBody())` : cela accepterait potentiellement des colonnes contrôlées par le client.

## Répartition possible

A : validation/insertion/doublons. B : liste/détail/summary. C : revue des protections et tests d’intégration. Convenez des fichiers de routes pour éviter les collisions. Le mécanisme login fourni doit être compris et vérifié, pas revendiqué comme entièrement écrit par l’étudiant.

## Vous transmettez

Au front : exemples 201/422/409 avec vrais noms de champs. Aux données : besoins de contraintes et requêtes. Aux exports : groupes normalisés et filtres. À QA : commande tests et routes refusées. À infra : nouvelles variables éventuelles avec valeur type, jamais un secret.

## Terminé lorsque

Une inscription correcte retourne 201 après écriture ; une invalide ne crée rien ; un doublon concurrent crée une seule ligne ; toutes les lectures/export privées refusent un client anonyme ; le total est exact sur plusieurs pages ; les erreurs n’exposent ni SQL ni données. Vos tests expliquent pourquoi les comportements sont corrects.
