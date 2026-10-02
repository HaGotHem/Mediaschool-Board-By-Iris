# Pôle 5 — Tableau de bord, récapitulatif et exports

**Votre responsabilité :** l’équipe du salon consulte rapidement les fiches et reçoit des chiffres identiques à l’écran, en CSV et en PDF.

## Missions et ordre

1. Explorer `admin.html`/`admin.js`. La connexion fonctionne ; les compteurs « — » et le bouton de test ne constituent pas le tableau de bord terminé.
2. BOARD-01 : construire liste/cartes, fiche détail et pagination. Charger depuis les routes authentifiées, afficher les saisies avec `textContent`.
3. BOARD-02 : ajouter filtres et récapitulatif. Montrer salon et périmètre, état de chargement, vide, erreur et session expirée.
4. EXP-01 : brancher la classe CSV sur la synthèse commune et servir une pièce jointe protégée.
5. EXP-02 : adapter l’exemple Dompdf et vérifier les libellés longs, accents et pages multiples. Même source de chiffres que le CSV.
6. EXP-03 : ajouter le formulaire d’envoi, format, destination, résultat SMTP et contrôle serveur. Le destinataire saisi doit être autorisé par configuration. Ne déclenchez pas de mail lors du chargement d’un écran.

## Répartition possible

A : écran de consultation et états. B : écran de synthèse et filtres. C : routes d’export/mail et tests. À deux, fusionnez A/B et gardez C comme responsabilité distincte. Les exports peuvent être codés par le pôle API si vos effectifs l’exigent : nommez le responsable.

## Exemples fournis

`backend/src/Export/` contient des aides de génération CSV/PDF et SMTP. `backend/examples/export-demo.php` génère des résultats fictifs. Vous devez les brancher sur les groupes réellement calculés et sur les réponses Slim. Aucun export ne se stocke dans `public/`. Le mail joint le contenu généré et pas un chemin fourni par le navigateur.

Le statut « accepted » signifie que le relais a accepté l’envoi. Ce n’est ni une preuve de réception ni une promesse d’envoi si SMTP est absent. Une erreur reste une erreur visible.

## Vous transmettez

À API/données : contrat des groupes et filtres. Au front public : éventuelles modifications du client API partagé. À infra : paramètres SMTP, adresse émettrice et politique de destinataires. À QA : exports fictifs et scénarios de session/SMTP en panne.

## Terminé lorsque

Le même jeu fictif donne les mêmes groupes et le même total partout. Les téléchargements refusent les anonymes, les caractères sont corrects et les cellules ne deviennent pas des formules. Un utilisateur retrouve une fiche sur mobile, exporte un récapitulatif et comprend le résultat de l’envoi. Les compteurs viennent du serveur, pas d’un tableau de la page courante.
