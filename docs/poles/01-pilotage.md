# Pôle 1 — Pilotage et intégration

**Votre responsabilité :** faire converger une seule version déployable et rendre visible le travail de chacun. Le coordinateur prend également une tâche technique (test d’intégration, route, script ou composant).

## Ce que vous faites dans l’ordre

1. Remplir les noms et relecteurs dans la répartition. Vérifier que chacun sait montrer une production personnelle.
2. Lire le PDF transcrit et `01-besoins.md`. Fixer les hypothèses, consigner les réponses de la directrice sans bloquer les travaux indépendants.
3. Réserver la cible avec l’infrastructure ; obtenir domaine, proxy, contacts et disponibilité SMTP.
4. Vérifier avec Merise/API les champs, ID, facultatifs et règle de doublon avant développement divergent.
5. Mettre les tâches du backlog dans un tableau de suivi ; contrôler dépendances et temps restant aux trois rendez-vous.
6. Faire intégrer un premier parcours réel au plus tôt : formulaire → API → base → fiche admin.
7. Relire les petites PR, lancer la CI, organiser la recette croisée, puis geler le code métier.
8. Remplir PV, transmission et manuel avec résultats réels. Faire constater les éventuels manques PDF/SMTP par la directrice.

## Fichiers dont vous êtes responsable

`livrables/REPARTITION.md`, `livrables/DECISIONS.md`, `livrables/deploiement/TRANSMISSION.md`, le statut du backlog et le PV. Vous veillez à l’alignement des guides, sans vous attribuer les modèles ou tests de vos camarades.

## Ce que vous demandez aux autres pôles

À Merise : migration et choix d’unicité. À API : réponse réelle 201 puis consultation. Au front : états d’erreur et mobile. À exports : mêmes totaux pour trois sorties. À QA : anomalies prioritaires, sans « OK » de convenance. À infrastructure : URL, santé, restauration et QR.

## Votre preuve et votre définition de terminé

Répartition nominative, décisions datées, PR intégrées, écarts expliqués, tâche technique personnelle et démonstration du paquet par un autre étudiant. Vous savez dire ce qui fonctionne, ce qui manque, qui l’a réalisé et sur quelle version cela a été testé. Une branche compilée seule ne constitue pas une livraison.
