# 11 — Guide de l’équipe du salon

Ce mode d’emploi décrit la version attendue. Complétez les champs de transmission et faites-le vérifier après développement ; dans le starter, les fonctions de consultation/export sont encore à réaliser.

## Enregistrer une visite

Présentez le QR code ou l’URL imprimée. Le visiteur remplit ses coordonnées et son projet, puis appuie sur « Enregistrer ma visite ». La confirmation et sa référence signifient que la fiche a été enregistrée. Un message d’erreur demande de corriger les champs ou de réessayer ; il ne confirme pas une visite.

Si un doublon est signalé, un membre habilité recherche la fiche dans l’espace équipe. Ne demandez pas au visiteur de modifier artificiellement son nom ou son e-mail pour contourner le contrôle. Cette collecte n’est pas une inscription administrative dans l’établissement.

## Consulter les fiches

Ouvrez `/admin.html` sur l’URL finale. Connectez-vous avec le compte remis par l’exploitant. Après connexion, choisissez une école et/ou un niveau si nécessaire, puis consultez la liste et le détail. Ne partagez pas le mot de passe et ne laissez pas un écran connecté sans surveillance. À la fin, utilisez « Se déconnecter ».

## Récapitulatif, CSV et PDF

Le récapitulatif compte les fiches du salon par école et niveau. Les filtres affichés définissent le périmètre ; réinitialisez-les pour obtenir le salon entier. Les boutons CSV et PDF téléchargent la même synthèse. Le CSV s’ouvre dans un tableur ; vérifiez accents et colonnes. Le PDF peut être imprimé.

## Envoyer le récapitulatif

Saisissez une adresse autorisée, choisissez CSV ou PDF et vérifiez les filtres. Un message indique si le serveur de messagerie a accepté l’envoi. Il ne garantit pas que le destinataire a lu le message. Une destination refusée est à faire ajouter par l’exploitant si elle est légitime. Une erreur ne doit pas déclencher de clics répétés ; faites vérifier le service.

## En cas de problème

| Problème | Action de l’équipe |
|---|---|
| QR ne s’ouvre pas | Essayer l’URL imprimée et un réseau mobile, contacter le responsable |
| Formulaire indique indisponible | Ne pas promettre un enregistrement ; utiliser le moyen de secours validé |
| Erreur sur un champ | Corriger la valeur sans refaire toute la fiche |
| Connexion expirée | Se reconnecter ; ne pas laisser le mot de passe au visiteur |
| Export/mail échoue | Contacter le responsable avec heure et message, sans envoyer une capture nominative sur un canal public |
| Correction ou suppression demandée | Transmettre au contact indiqué dans la notice ; une personne habilitée traite la demande |

Le moyen de secours est le formulaire Microsoft existant communiqué par l’établissement. Les coordonnées du contact technique, l’URL finale et les horaires de disponibilité sont à renseigner dans `livrables/deploiement/TRANSMISSION.md` et dans la version remise à l’équipe.
