# Pôle 3 — Formulaire mobile public

**Votre responsabilité :** un visiteur comprend le formulaire et obtient un résultat fiable sans assistance. Lisez `06-front.md`, les dix champs et les erreurs du contrat.

## Missions et ordre

1. FRONT-01 : explorer `index.html`, `src/form.js`, `src/api.js`, `src/style.css`. Tester les listes réelles. Ajuster les champs seulement selon décision commune.
2. FRONT-02 : intégrer le POST réel et la confirmation avec UUID, désactiver le bouton pendant l’envoi et prévoir une nouvelle fiche après réussite.
3. FRONT-03 : afficher `error.fields` près des champs, attributs aria et focus. Conserver la saisie si 422, 409, 429, 503 ou panne réseau.
4. Avec le coordinateur, remplacer la notice provisoire et supprimer l’encart de starter après recette.
5. FRONT-04 : tester à 360/1280 px et sur un téléphone, au clavier, avec date, zéro initial du téléphone et noms accentués.

## À ne pas confondre

Le `required` du HTML guide le visiteur ; la validation PHP protège le service. Vous ne prenez pas en charge la DB depuis le navigateur. Un retour 501/503 n’est pas une confirmation. Les listes sont servies par l’API : ne créez pas un deuxième catalogue codé en dur qui divergerait du SQL.

## Fichiers et découpage entre étudiants

Étudiant A : structure/formulaire/notice. Étudiant B : erreurs, états, confirmation et tests. Le fichier `api.js` est partagé avec le back-office : convenez des modifications avec lui. Si vous êtes un seul étudiant, gardez cet ordre et faites relire par API/QA.

## Vous transmettez

À API : payload conforme et exemples d’erreurs à afficher. À QA : URL, scénarios et captures fictives. À infrastructure : parcours testé et besoin d’URL publique ; vous ne générez pas un QR localhost.

## Terminé lorsque

Un testeur externe remplit le formulaire, corrige une erreur et retrouve exactement une fiche en base puis dans l’espace protégé. Il comprend ce que confirme sa visite. Les erreurs gardent les saisies ; aucun débordement mobile ni injection HTML. Vos commits et preuves individuelles distinguent vos ajouts du HTML de départ.
