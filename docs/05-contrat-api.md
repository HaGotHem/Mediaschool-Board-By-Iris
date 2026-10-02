# 05 — Contrat API partagé

Ce document fixe les échanges entre pôles. Les routes sont préfixées par `/api`. L’API reçoit du JSON UTF-8 ; un formulaire HTML sans JavaScript n’est pas le contrat de cette première version. Les retours réussis utilisent `{ "data": ... }`, les erreurs `{ "error": { "code", "message", "fields" } }`.

Les identifiants de référentiels sont des **entiers**, les champs optionnels vides sont `null` ou une chaîne vide pour `remark`. Les objets JSON, booléens ou tableaux ne doivent pas être convertis silencieusement en chaînes. Refusez les propriétés inconnues sensibles (`id`, `event_id`, `registered_at`, `admin`, etc.) ou ignorez-les explicitement par une liste fermée documentée.

## Routes et responsabilités

| Route | Accès | État du starter | Travail attendu |
|---|---|---|---|
| `GET /health` | Public | Fonctionnel | Santé technique, aucun détail de connexion |
| `GET /references` | Public | Fonctionnel | Quatre listes et salon actif |
| `GET /auth/session` | Public | Fonctionnel | État de session + token CSRF |
| `POST /auth/login` | Public + CSRF | Fonctionnel | Conserver et tester le mécanisme fourni |
| `POST /auth/logout` | Auth + CSRF | Fonctionnel | Fermer l’accès et vider l’écran |
| `POST /registrations` | Public | 503 fermé, puis 501 | Validation, insertion, doublon, référence |
| `GET /admin/registrations` | Auth | 501 | Liste paginée filtrable |
| `GET /admin/registrations/{id}` | Auth | 501 | Détail du salon actif ou 404 |
| `GET /admin/summary` | Auth | 501 | Totaux par école/niveau |
| `GET /admin/exports/csv` | Auth | 501 | Téléchargement du récapitulatif |
| `GET /admin/exports/pdf` | Auth | 501 | Même récapitulatif, format PDF |
| `POST /admin/summary/email` | Auth + CSRF | 501 | Export joint à destination autorisée |

Un login ne protège pas une route automatiquement : gardez toutes les routes personnelles dans le groupe `/admin`. Une personne non connectée reçoit 401, y compris sur les téléchargements. Une action modifiant l’état sans token CSRF reçoit 403. Le formulaire public, anonyme, a une limitation de débit au proxy ; prévoyez également sa protection serveur si la cible permet d’atteindre PHP autrement.

## Référentiels

`GET /references` retourne `schools`, `current_classes`, `entry_levels`, `specialties`, chacun sous forme `[{ "id": 3, "code": "IRIS", "label": "IRIS" }]`, ainsi que `event: { "id": 1, "label": "...", "event_date": "2026-10-03" }`. Les ID sont stables dans les migrations. Le front n’enregistre jamais un texte libre à la place d’un ID pour ces listes.

## Inscription

```http
POST /api/registrations
Content-Type: application/json

{
  "last_name": "Démonstration",
  "first_name": "Camille",
  "birth_date": "2008-04-12",
  "phone": "+33 6 00 00 00 00",
  "email": "camille.demo@example.test",
  "current_class_id": 3,
  "school_id": 3,
  "entry_level_id": 7,
  "specialty_id": 5,
  "remark": "Je souhaite découvrir le développement web."
}
```

Après INSERT effectif : **201** et `{ "data": { "reference": "UUID-généré", "message": "Votre visite est enregistrée." } }`. Ne retournez ni coordonnées, ni hash, ni ID de compte, ni SQL. La référence ne doit pas donner accès à une fiche publique.

Erreur métier : **422** avec des noms de champs alignés sur le formulaire :

```json
{"error":{"code":"VALIDATION_FAILED","message":"Corrigez les champs indiqués.","fields":{"email":"Adresse e-mail invalide.","birth_date":"Date de naissance invalide."}}}
```

Doublon : **409**, code `DUPLICATE_REGISTRATION`, message générique : « Une fiche identique a déjà été enregistrée pour ce salon. L’équipe peut vérifier votre inscription. » N’exposez pas la fiche existante. Limite : **429** avec `Retry-After`. Collecte fermée : **503** `REGISTRATIONS_CLOSED`. JSON malformé : **400** ; média non accepté : **415** à traiter dans la route/middleware métier. Défaillance inattendue : **500**, message neutre et trace technique sans saisies.

La validation se fait avant insertion : champs requis, types, longueurs Unicode, date avec parsing strict, téléphone, mail, existence des ID. `required` dans HTML ne protège pas l’API contre un client direct.

## Session et connexion

1. Le front appelle `/auth/session` avec cookies autorisés.
2. Il récupère `data.csrf_token`, même avant connexion.
3. Il POSTe `{ "username": "equipe", "password": "..." }` à `/auth/login` avec `X-CSRF-Token`.
4. Sur 200, il garde le nouveau token **en mémoire** et affiche l’écran.
5. Le cookie de session est `HttpOnly`, `SameSite=Lax`, `Secure` si l’URL est HTTPS. Ne lisez pas le cookie en JS et ne stockez pas le mot de passe.
6. Une déconnexion est un POST protégé ; une expiration provoque 401 et le retour à la connexion.

Le compte est créé par la CLI. Le serveur compare un hash avec `password_verify`. La limite de connexion fournie utilise une empreinte HMAC de l’IP et un compteur atomique : 20 essais par fenêtre de 15 minutes. Sur un réseau partagé, cette limite concerne plusieurs personnes ; testez l’usage et adaptez la configuration si nécessaire. Ne faites pas confiance aux en-têtes d’IP envoyés librement par le client.

## Liste et détail

Paramètres communs : `school_id`, `entry_level_id`, facultatifs, entiers positifs. Liste : `page=1`, `per_page=20`, maximum 100. Toute valeur invalide → 422. Tri serveur fixe ; pas de clause `ORDER BY` passée depuis le navigateur.

```json
{
  "data": {
    "items": [{"id":1,"last_name":"Démonstration","first_name":"Camille","school":"IRIS","entry_level":"Iris > BTS 1","registered_at":"2026-10-03T09:15:00+02:00"}],
    "pagination": {"page":1,"per_page":20,"total":1,"pages":1}
  }
}
```

Le détail renvoie les dix champs, les libellés de leurs référentiels et la date de saisie. Au minimum, documentez le mapping utilisé. Une ID inexistante ou appartenant à un autre salon → 404. La liste synthétique n’affiche pas les coordonnées complètes ; le détail reste protégé. Le front utilise `textContent`, pas `innerHTML`, pour afficher les saisies.

## Récapitulatif et téléchargements

Les routes summary/exports acceptent les mêmes filtres école/niveau ; le salon actif est fixé côté serveur. Sans filtre, toute la collecte du salon. Une seule fonction de requête produit les groupes pour les trois sorties.

```json
{
  "data": {
    "event": {"id":1,"label":"Salon Studyrama — 3 octobre 2026"},
    "filters": {"school_id":null,"entry_level_id":null},
    "groups": [{"school_id":3,"school":"IRIS","entry_level_id":7,"entry_level":"Iris > BTS 1","count":12}],
    "total":12,
    "generated_at":"2026-10-03T12:00:00+02:00"
  }
}
```

CSV : `text/csv; charset=utf-8`, BOM UTF-8, séparateur `;`, en-tête `École;Niveau;Nombre`, lignes des groupes puis `TOTAL;;12`. `Content-Disposition: attachment; filename="recap-salon-2026-10-03.csv"`. À zéro : en-tête + total zéro. Le nom de fichier est construit côté serveur et ne contient pas de saisie.

PDF : `application/pdf`, pièce jointe nommée côté serveur. Titre de l’application, salon, date de génération, filtres, table et total. Dompdf n’a pas accès aux URLs distantes ; le texte est échappé. Les PDF et CSV ne restent pas dans un répertoire public.

## Envoi SMTP

```json
{"recipient":"adresse-autorisée@domaine-de-lecole.fr","format":"pdf","filters":{"school_id":3,"entry_level_id":7}}
```

`recipient` doit être une adresse valide et figurer dans `SUMMARY_RECIPIENT_ALLOWLIST` configurée par l’exploitant. Le champ est saisi librement dans l’écran mais la politique est vérifiée côté serveur. `format` appartient à `csv|pdf`. Le serveur génère la pièce jointe : il n’accepte ni fichier uploadé ni chemin arbitraire.

Succès **200** : `{ "data": { "status": "accepted", "message": "Le relais de messagerie a accepté l’envoi." } }`. Erreur validation 422 ; destination non autorisée 403 ; SMTP absent/indisponible 503 ; pas de message donnant les identifiants SMTP. Limitez les envois répétés côté serveur et bloquez le bouton durant la demande. Le retry n’est jamais automatique après un résultat ambigu.

Les bibliothèques et une classe exemple sont fournies. Les tests utilisent des faux messages ou un relais de test ; aucun étudiant ne doit envoyer des données nominatives à un compte personnel pour vérifier son code.

## Aide pour tester

[requests.http](../backend/examples/requests.http) fournit des requêtes utilisables dans PhpStorm/IntelliJ ou un client HTTP. Remplacez le token CSRF et gardez les cookies du même client. Chaque nouvelle route doit avoir un cas normal, un cas invalide et un cas sans authentification s’il y a des données privées.
