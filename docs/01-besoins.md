# 01 — Comprendre la demande avant de développer

## Le contexte et les acteurs

La directrice souhaite disposer d’un outil pour le salon du samedi 3 octobre 2026. L’application s’appelle **Mediaschool Board by Iris Nice**. Les visiteurs utilisent leur téléphone ; les personnels du salon utilisent un navigateur mobile ou un ordinateur. Les étudiants réalisent et testent le service le vendredi 2 octobre. L’infrastructure est confiée à un responsable technique ou à des étudiants SISR.

Trois acteurs suffisent : visiteur anonyme, membre authentifié de l’équipe, exploitant technique. Il n’existe ni compte visiteur ni espace de candidature. Le visiteur enregistre ses coordonnées et son intérêt ; cela ne constitue pas une admission, une inscription administrative dans l’école, ni une vérification physique de présence par un agent.

## Hypothèses de travail explicites

| Sujet incomplet dans la demande | Hypothèse retenue pour avancer | Conséquence |
|---|---|---|
| « Valider sa venue » | Enregistrement de la visite au salon, horodaté côté serveur | Un seul clic final et une confirmation après écriture en base |
| « Par promo » | Regroupement par école visée **et** niveau d’inscription | IRIS/BTS 1 et ECS/B1 sont des lignes différentes |
| Nombre de souhaits | Une école, un niveau et au plus une spécialité par fiche | Un choix multiple nécessiterait un autre MCD et d’autres statistiques |
| Événement | Un salon actif, identifié par `EVENT_ID=1` | Le serveur choisit le salon ; le navigateur ne choisit pas un événement arbitraire |
| Édition des saisies | Affichage lisible et export du récapitulatif ; modification des fiches hors priorité initiale | Le détail est obligatoire ; un formulaire de correction est une évolution |
| Export demandé | CSV et PDF du récapitulatif agrégé par promo | Pas de coordonnées personnelles dans le mail de synthèse |
| Envoi | Envoi par le serveur via SMTP à une adresse saisie et autorisée | Pas de `mailto:` présenté comme un envoi de pièce jointe |
| Charge | Salon de taille modeste, quelques centaines de fiches | Pagination côté serveur, sans mécanisme temps réel complexe |
| Doublon | Même salon + mail normalisé + nom/prénom normalisés = même fiche | Deux personnes partageant un mail restent possibles si leurs noms diffèrent |

Le coordinateur peut demander une décision à la directrice sur la définition de « promo », les destinataires et les champs nécessaires. Pendant ce temps, les équipes avancent sur ce contrat. Toute décision qui change une règle se note dans `livrables/DECISIONS.md` et se répercute dans le contrat, les données et les tests.

**Les listes ne définissent pas un catalogue de formations validé.** Le PDF mélange niveaux génériques et niveaux IRIS. N’inventez pas de correspondances ECS/PSL/NSS ou de filtrage définitif. Dans la première version, reprenez toutes les valeurs. Une incompatibilité ne peut être bloquée qu’après obtention d’une règle explicite ; un message d’aide est préférable à une interdiction inventée.

## Les dix champs

| Champ visible | Requis dans le PDF / MVP initial | Type et limites du contrat | Aide utilisateur |
|---|---|---|---|
| Nom | Oui | Texte Unicode, 1 à 100 caractères après trim | Conserver accents, apostrophes et tirets |
| Prénom | Oui | Texte Unicode, 1 à 100 caractères | Ne pas imposer les majuscules |
| Date de naissance | Oui | Date civile réelle, non future, `YYYY-MM-DD` | Calendrier natif ; pas de conversion de fuseau |
| Téléphone | Oui | Texte, 8 à 15 chiffres après retrait des séparateurs ; `+` initial possible ; 30 caractères saisis maximum | Conserver le zéro initial ; accepter espaces et préfixe international |
| Mail | Oui | Adresse syntaxiquement valide, 254 caractères maximum | Trim et normalisation retenue documentée |
| Classe actuelle | Non | ID de classe existant ou `null` | « Non renseignée » |
| École visée | Oui | ID d’école existant | ECS, PSL, IRIS ou NSS |
| Niveau d’inscription | Oui | ID de niveau existant | Liste exacte du formulaire |
| Spécialité envisagée | Non | ID de spécialité existant ou `null` | « Je ne sais pas encore » |
| Remarque | Non | Texte brut, 0 à 1 000 caractères | Ne pas demander des données sensibles ni les coordonnées des parents |

Le PDF rend naissance et téléphone obligatoires. Cette règle est conservée comme base pédagogique ; la directrice doit justifier leur nécessité pour la collecte réelle et valider la notice. Si ces informations deviennent facultatives, modifiez ensemble HTML, validation PHP, nullabilité SQL et recette. N’ajoutez pas d’âge minimum ou d’exclusion des mineurs sans besoin validé.

## Parcours et critères d’acceptation

**Visiteur.** Après scan du QR, le formulaire s’ouvre sans connexion. Les dix champs sont utilisables à 360 px de largeur, avec un calendrier et des claviers adaptés. Les valeurs facultatives peuvent rester vides. Une erreur indique le champ à corriger sans effacer les autres saisies. Une réussite apparaît seulement après insertion et donne une référence non prédictible. Un échec réseau n’est jamais une réussite.

**Équipe.** La connexion protège l’API et pas seulement l’écran. Après authentification, les fiches sont paginées, triées de la plus récente à la plus ancienne. La fiche détail reprend les champs enregistrés. Les filtres portent sur école et niveau. Le récapitulatif utilise le même périmètre que les exports. Le total correspond à la somme des groupes. À zéro inscription, l’écran et les exports restent compréhensibles.

**Export et envoi.** CSV lisible dans un tableur, accents conservés, cellules neutralisées contre les formules. PDF lisible avec titre, salon, génération, filtres, groupes et total. Le serveur génère et joint l’un des deux formats. L’interface ne dit « livré » ni « reçu » : elle indique que le relais SMTP a accepté l’envoi. Une panne SMTP affiche une erreur et ne supprime aucune fiche.

**Exploitant.** Installation documentée, secrets hors Git, HTTPS, volume de base, sauvegarde et restauration testées, journaux sans formulaires, compte d’exploitation créé dans un terminal. Le QR code est testé sur le réseau mobile après fixation de l’URL.

## Priorités pour vendredi

- **P0 :** formulaire, sauvegarde avec validation, doublons, connexion, liste/détail, statistiques, export CSV, protection des données, paquet déployable et recette.
- **P1 :** PDF et envoi SMTP du récapitulatif. Ce sont des demandes réelles de la directrice : on ne les retire pas du périmètre. Elles doivent être traitées avant la livraison complète ; si elles manquent à l’heure de bascule, le coordinateur l’annonce et fait acter une livraison réduite.
- **P2 :** édition/suppression par interface, recherche avancée, graphiques, gestion de plusieurs salons et comptes depuis l’écran.

Le délai ne justifie jamais un espace admin public, un mot de passe codé en dur ou une fausse confirmation. En cas d’empêchement, le formulaire Microsoft existant reste le moyen de secours opérationnel ; il ne faut pas lancer une collecte douteuse en parallèle.

## Hors périmètre

Paiement, candidature complète, signature, scan de pièces d’identité, upload de fichiers, inscription multi-formations, CRM, newsletter, reconnaissance de présence, application native et statistiques publicitaires. Aucune fonctionnalité de prospection n’est ajoutée implicitement au formulaire.
