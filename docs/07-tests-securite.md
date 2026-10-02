# 07 — Tester le service et produire une recette utile

## Deux niveaux de vérification

`composer test` vérifie les exemples isolés du socle ; `composer smoke` vérifie Slim, les référentiels et les protections avec la base. La CI exécute ces tests et construit les images. **Une CI verte du starter ne prouve pas que les inscriptions sont développées.** Les tâches métier doivent ajouter leurs tests, et la recette finale doit être effectuée dans un navigateur, sur un téléphone et sur la cible.

Le pôle QA travaille dès la première heure : il prépare les cas avec le contrat, ne connaît pas à l’avance le détail du code et note les résultats observés. Il ne remplit jamais automatiquement « OK ». Utilisez `livrables/tests/RAPPORT.md` avec commit, environnement, testeur, attendu, observé, verdict et anomalie.

## Matrice minimale de tests

| ID | Scénario | Résultat attendu | Responsable principal |
|---|---|---|---|
| T01 | Champs requis valides, facultatifs vides | 201, une fiche réelle, UUID, affichage confirmé | Front/API |
| T02 | Tous champs valides, accents et apostrophes | Valeurs conservées, aucune casse SQL/HTML | API/données |
| T03 | Champ obligatoire absent ou chaîne blanche | 422, champ nommé, zéro INSERT | API |
| T04 | Date `2026-02-30`, date future | 422, pas de normalisation silencieuse | API |
| T05 | Téléphone avec zéro initial ou `+33` | Valeur conservée selon règle publiée | API/front |
| T06 | E-mail invalide, >254 caractères | 422, aucune fiche | API |
| T07 | ID de liste inconnu, ID chaîne non numérique, booléen ou tableau | 422, pas d’erreur SQL visible | API |
| T08 | Double clic / deux requêtes simultanées identiques | Une ligne, deuxième requête contrôlée | API/données |
| T09 | Même mail, personnes de noms différents | Deux fiches autorisées | Données |
| T10 | Remarque `<script>alert(1)</script>` | Texte affiché sans exécution | Back-office |
| T11 | Appel direct liste/détail/stats/export sans session | 401 et aucune donnée | QA/sécurité |
| T12 | Mauvais mot de passe puis trop d’essais | 401 neutre puis 429, pas d’énumération de comptes | QA/sécurité |
| T13 | Login/logout/email sans token ou avec token périmé | 403 ; aucune action sensible exécutée | QA/sécurité |
| T14 | Session déconnectée ou inactive >30 min | 401, écran privé vidé, reconnexion possible | Back-office |
| T15 | Plus de 20 fiches ; pagination et filtrage | Aucun oubli/duplicata, total de tout le périmètre | API/back-office |
| T16 | Plusieurs écoles/niveaux | Somme des groupes = total ; filtre cohérent | Données |
| T17 | Zéro fiche / filtre sans résultat | Écran vide explicite, total 0, export utilisable | Back-office |
| T18 | CSV avec accents, `;`, guillemets et cellule `=...` | Colonnes correctes et aucune formule exécutée | Exports |
| T19 | PDF multipage et libellés longs | Table lisible, total correct, pas de texte coupé | Exports |
| T20 | Destinataire autorisé / interdit / SMTP indisponible | Succès explicite ou 403/503 ; aucune fuite de secret | Exports/sécurité |
| T21 | Échec réseau pendant envoi public | Pas de fausse confirmation, saisie conservée | Front |
| T22 | Redémarrage de la pile | Fiches toujours présentes, migration non rejouée | Infra |
| T23 | Sauvegarde puis restauration sur cible de test isolée | Même nombre de fiches et mêmes référentiels | SISR/infra |
| T24 | Scan du QR en 4G/5G sur téléphone | URL HTTPS finale, formulaire utilisable | Infra/front |
| T25 | 360 px, 1280 px, clavier, calendrier natif | Pas de débordement, labels et focus utilisables | Front/QA |
| T26 | Corps trop grand, JSON invalide, content-type incorrect | Erreur contrôlée, aucune insertion | API/infra |
| T27 | Inscriptions fermées | 503 explicite, pas de collecte | API/QA |
| T28 | Réinstallation depuis le tag sur machine propre | Build, migration, compte, accès réussis avec guide | Infra |

## Tests à ajouter dans le code

Le validateur peut être testé sans serveur : normalisations, longueurs, dates, types inattendus. Le repository utilise une vraie base PostgreSQL de test, jamais celle du salon. Testez surtout les contraintes et requêtes qu’un simple contrôle visuel ne révèle pas : unicité concurrente, filtrage, portée du salon, agrégation. Le front requiert en plus une recette de parcours ; les tests PHP ne prouvent pas le bon comportement du calendrier ou du focus.

N’écrivez pas uniquement des tests qui recopient votre code. Un test de validation doit exprimer une règle du contrat. Un test de sécurité doit tenter l’accès refusé. Un test de restauration doit réellement restaurer et lire les données.

## Protections à conserver et à étendre

| Risque | Protection attendue | Preuve |
|---|---|---|
| Accès public aux contacts | Auth serveur sur toutes les routes admin et exports | T11, appel direct hors navigateur |
| Vol de mot de passe | Hash `password_hash`, TLS, aucun secret Git | Inspection code/config, HTTPS |
| CSRF | Token sur actions admin et login ; cookie SameSite | T13 |
| Injection SQL | Medoo/PDO, paramètres liés, noms de colonnes fermés | T02/T07 avec requêtes malveillantes fictives |
| XSS stockée | Affichage `textContent`, échappement PDF | T10 |
| Formules tableur | Neutralisation des cellules CSV | T18 |
| Brute force/spam | Limite login persistante, limite POST publique, contrôle des envois mail | T12 + essais contrôlés |
| Données perdues | Volume, migrations versionnées, sauvegarde et restauration | T22/T23 |
| Fuite par logs/export | Aucun corps de formulaire, export agrégé, fichiers hors webroot | Inspection logs et accès URL |

La limite publique Nginx protège le parcours normal. Si PHP est exposé sur un autre point d’accès, cette protection ne suffit plus : l’architecture doit empêcher ce contournement ou ajouter une limite applicative. La base n’a pas de port hôte dans Compose. Un proxy externe peut partager l’IP vue par Nginx entre visiteurs : configurez `real_ip` uniquement pour les adresses de proxies de confiance, avec l’exploitant. Ne faites jamais confiance à tout `X-Forwarded-For`.

## Information des personnes et décision de collecte

La notice visible doit être remplacée par un texte réel avant ouverture : identité du responsable du traitement et contact, finalités, base légale retenue par l’établissement, destinataires, durée de conservation justifiée, droits et point de contact. La collecte de naissance et téléphone doit être justifiée ; le modèle de PDF ne suffit pas à établir la conformité. Les remarques ne doivent pas inviter à saisir des données sensibles ou les coordonnées de tiers.

Un simple formulaire de visite n’autorise pas une newsletter. Ne mettez pas une case obligatoire « j’accepte le RGPD » présentée comme solution universelle. Si une finalité distincte nécessitant un consentement est réellement ajoutée, elle doit être explicitée et traitée séparément. Le coordinateur fait valider la notice et les champs par l’établissement et consigne la décision.

Les tests sont fictifs. Les captures pour le portfolio masquent toute donnée réelle ; les exports nominatifs et mots de passe n’entrent pas dans le dépôt. Les durées des fiches, sauvegardes et logs doivent être cohérentes avec la politique retenue. Une demande de rectification ou d’effacement est transmise au contact désigné et exécutée par une personne habilitée selon une procédure documentée.

## Décision de livraison

La recette distingue P0, P1 et évolutions. Aucun P0 bloquant ne reste ouvert. Un manque PDF/mail ne devient pas automatiquement acceptable : la directrice doit connaître et accepter le périmètre réduit. Le starter et ses tests ne sont pas une validation de mise en production. Le coordinateur remplit la checklist et le procès-verbal avec les résultats réels.
