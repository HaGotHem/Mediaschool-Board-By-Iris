# Sources et décisions de transcription

## Formulaire fourni : Salon.pdf

Titre lu : « Contacts Salon Studyrama 3 Octobre 2026 (Copie) ». Le document contient cinq pages. Champs : NOM*, PRENOM*, DATE DE NAISSANCE*, TELEPHONE*, MAIL*, CLASSE ACTUELLE, ECOLE VISEE*, NIVEAU D’INSCRIPTION*, SPECIALITE (facultative), REMARQUE. Les quatre listes sont transcrites dans `backend/database/migrations/002_references.sql`.

- Classes : Seconde, Première, Terminale, B1, B2, B3, M1 et Plus.
- Écoles : ECS, PSL, IRIS, NSS.
- Niveaux : BTS communication, B1, B2, B3, M1, M2, Iris > BTS 1, Iris > BTS 2, Iris > B3, Iris > M1, Iris > M2.
- Spécialités : Com Event, Crea Digit, DA, Manager du développement d’entreprise et commercial, Iris > SLAM, Iris > SISR, Iris > B3 AIS, Iris > B3 CSD, Iris > M1 IT Cybersécurité, Iris > M1 IT Dev, Iris M2 IT Cybersécurité.

Adaptations : nom/prénom en casse naturelle, texte d’aide de la remarque sans invitation à fournir des coordonnées de parents, séparation en blocs et choix facultatif explicite. Aucun mapping entre école/niveau/spécialité n’est inventé. Les exigences naissance/téléphone doivent être confirmées pour une collecte réelle.

Le fichier original et les annexes ne sont pas redistribués ici. Le professeur conserve les pièces fournies. Cette transcription permet aux étudiants de travailler sans dépendre d’un accès au formulaire externe.

## Pièces BTS fournies

- `RNCP40792 - BTS - Services informatiques aux organisations.pdf` : blocs, compétences et sous-compétences.
- `8 - BTS SIO - Annexe 8-1 - Tableau de synthèse - Epreuve E4 - BTS SIO 2024.xlsx` : tableau individuel des réalisations et compétences du bloc commun.
- `8 - BTS SIO - Annexes 8 - Epreuve E4 - BTS SIO 2024.docx` : critères et contribution personnelle au travail d’équipe.
- `9 - BTS SIO - Annexes 9 - Epreuve E5 - BTS SIO 2024.docx` : deux réalisations, preuves techniques et couverture conjointe du bloc 2.
- `10 - BTS SIO - Annexes 10 - Epreuve E5 - envt technologique - BTS SIO 2024.docx` : cadre applicatif, langages, SGBD, tests, versions et environnement élargi.
- Annexe E4 de 2023 : pièce historique fournie ; le rapprochement opérationnel utilise les modèles 2024 présents.

Le rapprochement se lit dans [les compétences](../10-competences-bts.md). La session et la circulaire applicables restent à confirmer par l’établissement.

## Documentation technique officielle consultée

- [Slim 4 : installation](https://www.slimframework.com/docs/v4/start/installation.html), [body parsing](https://www.slimframework.com/docs/v4/middleware/body-parsing.html), [middlewares et ordre](https://www.slimframework.com/docs/v4/concepts/middleware.html).
- [Medoo : configuration et PDO PostgreSQL](https://medoo.in/api/new), [select](https://medoo.in/api/select), [query](https://medoo.in/api/query).
- [daisyUI avec Vite](https://daisyui.com/docs/install/vite/), [composants](https://daisyui.com/components/).
- [Docker Compose : attente de santé des dépendances](https://docs.docker.com/compose/how-tos/startup-order/), [production](https://docs.docker.com/compose/how-tos/production/).
- [PHP : password_hash](https://www.php.net/manual/fr/function.password-hash.php), [validation stricte des dates](https://www.php.net/manual/fr/datetime.createfromformat.php).
- [Dompdf](https://github.com/dompdf/dompdf), [PHPMailer](https://github.com/PHPMailer/PHPMailer).
- [CNIL : informations sur les formulaires](https://www.cnil.fr/fr/passer-laction/rgpd-exemples-de-mentions-dinformation), [minimisation](https://www.cnil.fr/fr/definition/minimisation), [durées de conservation](https://www.cnil.fr/fr/passer-laction/les-durees-de-conservation-des-donnees).

Ces ressources servent à comprendre les composants ; elles ne remplacent ni les règles métier validées ni les preuves de recette.
