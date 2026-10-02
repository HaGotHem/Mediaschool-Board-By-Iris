# 10 — Compétences BTS SIO et preuves de réalisation

## Documents qui fondent ce rapprochement

Ce projet s’appuie sur les pièces fournies par le professeur : fiche RNCP40792, annexe 8/tableau de synthèse E4 de 2024, annexe 9 E5 de 2024 et annexe 10 environnement technologique E5 de 2024. Les intitulés ci-dessous sont ceux de ces documents. Les codes BC01/BC02/BC03/BC04/BC05 sont les identifiants de blocs **RNCP**, pas une nouvelle numérotation d’épreuves.

Les annexes fournies appellent E4 « Support et mise à disposition de services informatiques » et E5 « Administration des systèmes et des réseaux » ou « Conception et développement d’applications ». Conservez ces désignations lorsque vous travaillez sur ces modèles ; la circulaire applicable à votre session d’examen sera confirmée par le professeur. Ce guide ne remplace pas les documents officiels.

Une compétence est **mobilisable**, puis **démontrée par une contribution personnelle et des preuves**. Le fait que le dépôt comporte du code n’atteste pas que chaque étudiant l’a réalisé ni maîtrisé.

## Bloc commun — RNCP40792BC01

| Compétence officielle | Activités du projet | Preuves exploitables | Limite de couverture |
|---|---|---|---|
| Travailler en mode projet | Analyse, rôles, planning, backlog, intégration, analyse d’écarts | Répartition, décisions, PR, compte rendu des écarts | Forte si l’étudiant participe et explique sa contribution |
| Mettre à disposition des utilisateurs un service informatique | Tests d’intégration/acceptation, déploiement, accompagnement | Rapport, checklist, installation, manuel, démonstration | Déploiement uniquement pour ceux qui y contribuent effectivement |
| Gérer le patrimoine informatique | Recenser pile, normes, habilitations, continuité, sauvegarde/restauration | Inventaire technique, tests d’accès, dump et preuve de restauration fictive | Partielle ; pas de gestion exhaustive de parc dans ce mini-projet |
| Répondre aux incidents et aux demandes d’assistance et d’évolution | Suivi d’anomalies, diagnostic et corrections | Issues avec reproductions, correctif, non-régression, réponse adaptée | À déclarer seulement si incidents/demandes réellement traités |
| Développer la présence en ligne de l’organisation | Formulaire au nom de l’organisation et cadre juridique | Parcours public, notice, analyse des besoins et contribution au site | Partielle : aucun référencement/mesure de visibilité prévu ; site noindex |
| Organiser son développement professionnel | Environnement local, lecture de documentation, retour individuel | Fiche d’apprentissage et sources utilisées | Un projet d’une journée n’atteste pas une veille régulière ni toutes les sous-compétences |

Sous-compétences particulièrement documentables : « Analyser les objectifs et les modalités d’organisation d’un projet », « Planifier les activités », « Évaluer les indicateurs de suivi d’un projet et analyser les écarts », « Réaliser les tests d’intégration et d’acceptation d’un service », « Déployer un service », « Accompagner les utilisateurs dans la mise en place d’un service », « Mettre en place et vérifier les niveaux d’habilitation associés à un service » et « Gérer des sauvegardes ».

## Option SLAM — RNCP40792BC03

| Compétence officielle | Sous-compétences travaillées | Livrables et preuve individuelle |
|---|---|---|
| Concevoir et développer une solution applicative | Analyser un besoin exprimé et son contexte juridique ; participer à la conception de l’architecture ; modéliser ; exploiter le framework ; identifier/développer des composants ; échanges Web y compris de mobilité ; composants d’accès aux données ; intégration ; tests ; documentation ; environnement de développement/tests | Contrat, MCD et schéma, composant PHP/JS, Medoo, PR/CI, tests et guide expliqués par leur auteur |
| Assurer la maintenance corrective ou évolutive d’une solution applicative | Recueillir/analyser les informations de version ; évaluer la qualité ; analyser/corriger un dysfonctionnement ; mettre à jour documentation ; tests des éléments mis à jour | Anomalie réelle ou évolution explicitée, diagnostic, patch, non-régression et doc mise à jour |
| Gérer les données | Exploiter les données par requêtes ; concevoir/adapter une base ; administrer et déployer une base | MCD/MLD, contraintes, migrations, SQL agrégé, tests d’intégrité et installation |

La sous-compétence « Développer des fonctionnalités applicatives au sein d’un système de gestion de base de données » n’est pas automatiquement couverte par des requêtes exécutées en PHP. Une fonction SQL, une procédure ou un trigger répondant à un besoin réel peut la mobiliser, avec tests ; n’ajoutez pas un trigger artificiel au détriment du délai.

## Option SISR — RNCP40792BC02

| Compétence officielle | Activités possibles si les SISR réalisent le déploiement | Preuves |
|---|---|---|
| Concevoir une solution d’infrastructure réseau | Besoin, architecture, choix de cible/réseau, qualité/disponibilité, préparation des tests | Schéma, dossier de choix, port/proxy, risques et plan de test |
| Installer, tester et déployer une solution d’infrastructure réseau | Docker, reverse proxy HTTPS, volumes, sauvegarde, recette et documentation | Commandes commentées, configuration sans secrets, tests, guide d’exploitation |
| Exploiter, dépanner et superviser une solution d’infrastructure réseau | Administration, automatisation des sauvegardes, santé/logs, résolution d’incident | Scripts, preuve d’exécution, diagnostic et indicateurs observés |

Cette couverture est conditionnelle : elle n’appartient pas aux étudiants qui remettent uniquement le code au responsable externe. Le déploiement d’une pile simple mobilise des sous-compétences et ne représente pas à lui seul toute l’infrastructure exigée pour la certification.

## Cybersécurité — RNCP40792BC05 SLAM / BC04 SISR

| Compétence officielle pertinente | Mise en œuvre | Preuve attendue |
|---|---|---|
| Protéger les données à caractère personnel | Recenser collecte et risques, minimiser, notice, conservation, captures fictives | Dictionnaire, décision des champs, notice validée et procédure de droits |
| Sécuriser les équipements et les usages des utilisateurs | Gestion des accès/privilèges, information des utilisateurs, vérification de protection | Comptes, tests 401/403, manuel et secret management |
| Garantir la disponibilité, l’intégrité et la confidentialité des services informatiques et des données de l’organisation face à des cyberattaques | Risques, sauvegarde, accès fermé, contrôles et procédures | Analyse simple de risques, tests d’accès, restauration |
| Assurer la cybersécurité d’une solution applicative et de son développement (SLAM) | Sécurité dans le développement, prévention injections/XSS/CSRF, vérification des composants | Code, tests adverses, revue et diagnostic sans secrets |
| Assurer la cybersécurité d’une infrastructure réseau, d’un système, d’un service (SISR) | Configuration réseau/proxy et contrôle des protections sur cible | Schéma, HTTPS, ports, logs et tests externes |

Aucune couverture complète du bloc cybersécurité n’est annoncée : analyse d’incident, preuve électronique et contre-mesures doivent avoir été effectivement travaillées pour être revendiquées.

## Utiliser le projet dans le portfolio et les réalisations

Pour le tableau de synthèse du bloc commun, choisissez un intitulé court, dates réelles et liens vers vos productions. Cochez uniquement les compétences appuyées par votre travail. Écrivez « j’ai conçu la contrainte d’unicité et vérifié deux insertions simultanées », plutôt que « nous avons fait une application ». Les captures ne montrent pas des visiteurs réels.

Pour une fiche de réalisation de l’annexe E5 fournie, décrivez organisation, conditions, ressources, résultats et accès techniques. **Deux réalisations sont requises et doivent couvrir à elles deux l’ensemble des compétences du bloc 2 de l’option** selon l’annexe 9. L’annexe 10 décrit un environnement plus large : notamment deux solutions applicatives opérationnelles et, pour SLAM, au moins trois situations d’exécution citées. Un site responsive mobile reste du code exécuté dans un navigateur ; il ne devient pas une application native parce qu’on l’ouvre sur un téléphone.

PHP, JavaScript, Slim, Medoo, PostgreSQL, Git, tests et environnement Docker apportent des éléments utiles. Ce mini-projet doit être rapproché de votre autre réalisation et de l’environnement du centre, avec le professeur ; il n’est pas déclaré conforme à tout l’environnement d’examen par ce guide.

## Fiche individuelle à remplir

Une page suffit si elle est précise : mission, décisions, fichiers, commits/PR, cas testés, incident corrigé, explication d’une requête ou d’un composant et compétences démontrées. Ajoutez ce que vous avez utilisé du starter. Conservez votre source et votre export du MCD si vous l’avez conçu. L’étudiant doit pouvoir expliquer et modifier sa production devant une autre personne.
