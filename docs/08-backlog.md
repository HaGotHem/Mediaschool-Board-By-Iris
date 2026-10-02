# 08 — Backlog à répartir dès le démarrage

Utilisez les identifiants dans les branches, commits, PR et fiches individuelles. Les estimations sont indicatives, pour une équipe connaissant déjà la pile. Elles ne s’additionnent pas comme si une seule personne réalisait toutes les tâches. Les dépendances donnent l’ordre d’intégration ; plusieurs activités peuvent être préparées en parallèle.

| ID | Priorité | Tâche et livrable vérifiable | Pôle | Dépend de | Estimation |
|---|---|---|---|---|---:|
| PIL-01 | P0 | Attribuer noms, tâches, relecteurs et décisions initiales | Pilotage | — | 20 min |
| PIL-02 | P0 | Confirmer promo, champs, notice, destinataires ; tracer les hypothèses retenues | Pilotage | PIL-01 | 30 min |
| DATA-01 | P0 | MCD source + export avec associations/cardinalités et justification | Données | PIL-01 | 40 min |
| DATA-02 | P0 | MLD, dictionnaire, normalisation et règle de doublon | Données | DATA-01 | 30 min |
| DATA-03 | P0 | Migration `003_registrations.sql`, FK, NULL, unique et indexes | Données | DATA-02 | 35 min |
| DATA-04 | P0 | Jeu fictif distinct + requêtes liste/détail/summary testées | Données | DATA-03 | 40 min |
| API-01 | P0 | Validateur PHP avec types stricts, erreurs par champ et tests | API | DATA-02 | 60 min |
| API-02 | P0 | Insertion Medoo, UUID, doublon 23505, ouverture contrôlée | API | API-01, DATA-03 | 60 min |
| API-03 | P0 | Liste paginée et détail, portée salon, filtres liés | API | DATA-03 | 60 min |
| API-04 | P0 | Fonction de récapitulatif commune, total et filtres | API/données | DATA-04 | 40 min |
| SEC-01 | P0 | Tester sessions, accès, CSRF et limites ; corriger les nouvelles routes | API/QA | API-03 | 40 min |
| SEC-02 | P0 | Remplacer la notice et retirer les messages de développement | Front/pilotage | PIL-02 | 25 min |
| FRONT-01 | P0 | Adapter les champs/labels à la décision, états chargement et indisponibilité | Front public | PIL-02 | 25 min |
| FRONT-02 | P0 | Brancher l’envoi réel, référence, nouveau formulaire et état pending | Front public | API-02 | 40 min |
| FRONT-03 | P0 | Erreurs par champ, aria, focus, conservation des saisies | Front public | API-01 | 45 min |
| FRONT-04 | P0 | Recette mobile et clavier, calendrier, double clic, coupure réseau | Front public/QA | FRONT-02/03 | 35 min |
| BOARD-01 | P0 | Liste/cartes, pagination, états vide/erreur et fiche détail | Back-office | API-03 | 60 min |
| BOARD-02 | P0 | Filtres, récapitulatif réel, total et périmètre affiché | Back-office | API-04 | 40 min |
| EXP-01 | P0 | Route CSV protégée et bouton, mêmes groupes, test accents/formules | Exports | API-04 | 35 min |
| EXP-02 | P1 | Route PDF protégée et bouton, vérification visuelle multipage | Exports | API-04 | 45 min |
| EXP-03 | P1 | Envoi SMTP avec pièce jointe, allowlist, limite, états et erreurs | Exports | EXP-01/02, INF-01 | 60 min |
| QA-01 | P0 | Préparer matrice et données fictives sans attendre la fin | QA | PIL-01 | 30 min |
| QA-02 | P0 | Recette croisée T01–T28, anomalies suivies et preuves | QA | Parcours intégré | 60 min |
| INF-01 | P0 | Cible, domaine, HTTPS, accès exploitant et SMTP prévus | SISR/infra | PIL-01 | 45 min |
| INF-02 | P0 | Installation reproductible, migration, compte, secrets | SISR/infra | DATA-03, API-02 | 40 min |
| INF-03 | P0 | Sauvegarde et restauration sur cible de test isolée | SISR/infra | INF-02 | 40 min |
| INF-04 | P0 | URL finale, QR statique, scan 4G et support imprimé | SISR/infra | INF-02, QA-02 | 25 min |
| DOC-01 | P0 | Manuel équipe et fiche de transmission remplis | Tous/pilotage | Parcours intégré | 25 min |
| REL-01 | P0 | Gel, version/tag, paquet, checksum, PV de recette et remise | Intégration | QA-02, INF-03/04 | 30 min |
| IND-01 | P0 | Fiche individuelle, liens de preuve, préparation portfolio | Chaque étudiant | Sa tâche livrée | 20 min |

## Comment prendre une tâche

1. Inscrivez votre nom et le relecteur dans la répartition.
2. Relisez son contrat et ses dépendances ; si elles ne sont pas prêtes, préparez le test ou l’interface, puis aidez le pôle concerné.
3. Découpez les tâches de plus d’une heure si possible.
4. Ouvrez une branche avec l’ID ; rendez une PR courte et testable.
5. Déplacez la tâche dans terminé uniquement après intégration et preuve.

Le statut initial est « à faire » pour toutes les tâches métier. L’existence d’un exemple fourni ne vaut pas réalisation. Les étudiants QA ou Merise ont des productions propres ; ils ne se contentent pas de noter le travail des autres.

## Évolutions seulement après livraison

Correction de fiche par interface avec journal d’action, recherche nom/mail, purge automatisée selon politique validée, sélection d’événements, gestion des comptes, clé d’idempotence, export nominatif avec habilitation spécifique. Chacune exige son besoin, ses protections, ses tests et sa documentation ; aucune n’est ajoutée pour remplir du temps alors que la recette principale manque.
