# Pôle 4 — Guide backend

Guide pratique pour réaliser API-01 à API-04 et SEC-01. À lire avec la [fiche de pôle](04-backend.md), le [contrat API](../05-contrat-api.md) et la [roadmap](04-backend-roadmap.md).

> Les extraits de code sont des **modèles à adapter et tester**, pas du code à coller sans le comprendre. Chaque extrait est fait pour tenir avec la pile du starter (PHP 8.4, Slim 4, Medoo 2, PostgreSQL 17). Ce que vous n’avez pas exécuté n’est pas « vérifié ».

## 1. Ce qu’il faut avoir en tête

Votre responsabilité : *seuls des champs valides sont enregistrés, les routes privées le restent, le front reçoit des erreurs stables.* Quatre règles gouvernent presque toutes les décisions :

1. **Le client ne décide de rien d’important.** Salon (`EVENT_ID`), ID, UUID, date d’enregistrement, tri, noms de colonnes, nom de fichier : tout cela vient du serveur.
2. **Valider d’abord, écrire ensuite.** Un 201 n’est renvoyé qu’après un INSERT réussi. Jamais de fausse confirmation, jamais de 501 remplacé par un faux succès.
3. **La base garantit l’unicité**, pas un `SELECT` avant l’`INSERT`.
4. **Les erreurs et les logs ne contiennent ni saisie, ni SQL, ni secret.**

### Le trajet d’une requête

```
navigateur → Nginx (limite 10 req/min sur /api/registrations, corps ≤ 32 Ko)
          → PHP-FPM → public/index.php → src/app.php (Slim)
          → middlewares (corps JSON, session) → route → Database (Medoo) → PostgreSQL
          ← Json::send() / Json::error()
```

### Ce que le starter fait déjà

| Fourni | Où | Ce qu’il faut savoir |
|---|---|---|
| Réponses JSON | `src/Http/Json.php` | `Json::send($payload, $status)` ; `Json::error($code, $msg, $status, $fields)` → `{error:{code,message,fields}}` |
| Connexion paresseuse | `$db` dans `app.php` | `$db()` renvoie un `Medoo`, `ERRMODE_EXCEPTION`, requêtes préparées natives |
| Auth/CSRF | `src/Security/` | `AuthMiddleware` (401), `CsrfMiddleware` (403 sur les verbes non sûrs) |
| Limite de connexion | `LoginLimiter` | UPSERT atomique PostgreSQL ; 20 essais / 15 min par IP hachée |
| Gestionnaire d’erreurs | `app.php` | Journalise **seulement** la classe de l’exception et le statut |
| Exports | `src/Export/` | `SummaryExport::csv/pdf`, `SummaryMailer::assertRecipient/send` |
| Tests | `tests/unit.php`, `tests/smoke.php` | Pas de PHPUnit : petite fonction `check()`/`expect()` qui lève une exception |

## 2. Prise en main (30 min)

```bash
cp .env.example .env
docker compose up -d --build --wait
docker compose exec api php bin/create-admin.php equipe
docker compose exec api composer test
docker compose exec api composer smoke
```

Avec rechargement du PHP à chaud (recommandé pour développer) :

```bash
docker compose -f compose.yaml -f compose.dev.yaml up -d --build --wait
```

Réflexes quotidiens :

| Besoin | Commande |
|---|---|
| Erreurs PHP / API | `docker compose logs --tail=100 api` |
| Appliquer une migration | `docker compose exec api php bin/migrate.php` |
| Shell PHP | `docker compose exec api sh` |
| psql | `docker compose exec db sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"'` |
| Appliquer une variable `.env` | `docker compose up -d --force-recreate api` |

`docker compose down -v` **efface les données** : jamais en production, jamais pour « réparer ».

Les requêtes de test sont dans [`backend/examples/requests.http`](../../backend/examples/requests.http) (PhpStorm/IntelliJ ou extension REST client). Ajoutez-y un cas normal, un cas invalide et un cas sans authentification pour chaque route que vous créez.

## 3. Organisation du code

Le starter garde toutes les routes dans `src/app.php` pour qu’on les voie. À plusieurs, c’est une source de conflits Git. Découpez **en une petite PR initiale** :

```
backend/src/
├── app.php                    # middlewares + auth + require des fichiers de routes
├── Routes/
│   ├── registrations.php      # POST /api/registrations
│   └── admin.php              # groupe /api/admin (Auth + CSRF)
├── Registration/
│   ├── Validator.php          # pur : tableau → valeurs + erreurs (pas de base, pas de HTTP)
│   ├── Repository.php         # Medoo, ne reçoit que des valeurs validées
│   └── Filters.php            # parse page/per_page/school_id/entry_level_id
└── Summary/
    └── SummaryService.php     # UNE requête de groupes pour JSON, CSV, PDF, mail
```

L’autoload PSR-4 (`Board\` → `src/`) range les classes ; les fichiers de routes sont de simples fichiers `require`. Dans `app.php`, remplacez l’ancien bloc `POST /api/registrations` et le groupe `/api/admin` par :

```php
(require __DIR__ . '/Routes/registrations.php')($app, $db);
(require __DIR__ . '/Routes/admin.php')($app, $db);
return $app;
```

Chaque fichier de routes a cette forme :

```php
<?php
declare(strict_types=1);
use Board\Http\Json;
use Psr\Http\Message\{ServerRequestInterface as Request, ResponseInterface as Response};
use Slim\App;

return static function (App $app, Closure $db): void {
    // ...routes
};
```

**À ne pas casser :**

- `tests/smoke.php` fait `require src/app.php` et lit la variable **`$db`** définie là-dedans : gardez le nom `$db` et le `return $app`.
- Dans `admin.php`, le groupe se termine toujours par `->add(new CsrfMiddleware())->add(new AuthMiddleware())`, **dans cet ordre** : le dernier ajouté s’exécute en premier, donc un anonyme reçoit 401 avant d’être jugé sur son jeton CSRF.
- `POST /registrations` est public et sans CSRF *par contrat* (visiteur anonyme). C’est pourquoi on exige `Content-Type: application/json` (415 sinon) : un formulaire HTML d’un autre site ne peut pas l’envoyer.

## 4. API-01 — Le validateur

Un validateur **pur** : il reçoit le tableau, les ID valides et la date du jour, et renvoie `values` + `errors`. On le teste sans serveur.

### Règles (alignées sur [01 — Besoins](../01-besoins.md))

| Champ | Règle | Erreur (clé = nom du formulaire) |
|---|---|---|
| `last_name`, `first_name` | `string`, trim, 1–100 caractères Unicode (`mb_strlen`), pas de caractère de contrôle | `last_name`, `first_name` |
| `birth_date` | `YYYY-MM-DD`, **date réelle** (refuser `2026-02-30`), non future | `birth_date` |
| `phone` | trim, ≤ 30 caractères, chiffres/espaces/`.()-`, `+` initial, 8–15 chiffres | `phone` |
| `email` | trim, minuscules, ≤ 254, `FILTER_VALIDATE_EMAIL` | `email` |
| `school_id`, `entry_level_id` | **entier** JSON, présent dans le référentiel | idem |
| `current_class_id`, `specialty_id` | entier existant **ou `null`/absent** | idem |
| `remark` | `string` facultative, ≤ 1000, `''` si vide | `remark` |
| `id`, `event_id`, `reference`, `registered_at`, `admin` | **jamais acceptés** | le nom du champ |

Pièges de typage : `"3"`, `3.0`, `true`, `[3]`, `{"a":1}` ne sont **pas** des ID valides ; un nom envoyé sous forme de tableau ne devient pas la chaîne `"Array"`. `trim()` ne retire pas l’espace insécable ni les contrôles Unicode : utilisez une regex `/u`. Une chaîne en UTF-8 invalide fait renvoyer `null` à `preg_replace` : traitez-la comme invalide.

### Modèle

```php
<?php
declare(strict_types=1);
namespace Board\Registration;

final class Validator
{
    private const FORBIDDEN = ['id', 'event_id', 'reference', 'registered_at', 'admin'];

    /**
     * @param array<string,list<int>> $ids  schools, current_classes, entry_levels, specialties
     * @return array{values: array<string,mixed>, errors: array<string,string>}
     */
    public static function validate(array $body, array $ids, \DateTimeImmutable $today): array
    {
        $errors = []; $values = [];
        foreach (self::FORBIDDEN as $key) {
            if (array_key_exists($key, $body)) { $errors[$key] = 'Champ non autorisé.'; }
        }
        $values['last_name']  = self::name($body['last_name'] ?? null, 'last_name', 'Nom', $errors);
        $values['first_name'] = self::name($body['first_name'] ?? null, 'first_name', 'Prénom', $errors);
        $values['birth_date'] = self::birthDate($body['birth_date'] ?? null, $today, $errors);
        $values['phone']      = self::phone($body['phone'] ?? null, $errors);
        $values['email']      = self::email($body['email'] ?? null, $errors);
        $values['current_class_id'] = self::id($body['current_class_id'] ?? null, $ids['current_classes'], 'current_class_id', false, $errors);
        $values['school_id']        = self::id($body['school_id'] ?? null, $ids['schools'], 'school_id', true, $errors);
        $values['entry_level_id']   = self::id($body['entry_level_id'] ?? null, $ids['entry_levels'], 'entry_level_id', true, $errors);
        $values['specialty_id']     = self::id($body['specialty_id'] ?? null, $ids['specialties'], 'specialty_id', false, $errors);
        $values['remark']           = self::remark($body['remark'] ?? null, $errors);
        return ['values' => $errors === [] ? $values : [], 'errors' => $errors];
    }

    /** null si ce n'est pas une chaîne UTF-8 valide. */
    private static function trim(mixed $value): ?string
    {
        return is_string($value) ? preg_replace('/^[\s\p{Z}\p{Cc}]+|[\s\p{Z}\p{Cc}]+$/u', '', $value) : null;
    }

    private static function name(mixed $raw, string $field, string $label, array &$errors): ?string
    {
        $s = self::trim($raw);
        if ($s === null || $s === '') { $errors[$field] = "$label obligatoire."; return null; }
        if (mb_strlen($s) > 100 || preg_match('/\p{Cc}/u', $s)) { $errors[$field] = "$label invalide."; return null; }
        return $s;
    }

    private static function birthDate(mixed $raw, \DateTimeImmutable $today, array &$errors): ?string
    {
        // '!' remet l'heure à 00:00 ; l'aller-retour format() refuse 2026-02-30 (qui deviendrait le 2 mars).
        $d = is_string($raw) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $raw, $today->getTimezone()) : false;
        if ($d === false || $d->format('Y-m-d') !== $raw || $d > $today || $d->format('Y') < '1900') {
            $errors['birth_date'] = 'Date de naissance invalide.'; return null;
        }
        return $raw;
    }

    private static function phone(mixed $raw, array &$errors): ?string
    {
        $p = self::trim($raw);
        $digits = $p === null ? '' : (string)preg_replace('/\D/', '', $p);
        if ($p === null || mb_strlen($p) > 30 || !preg_match('/^\+?[0-9 .()\-]+$/', $p) || strlen($digits) < 8 || strlen($digits) > 15) {
            $errors['phone'] = 'Numéro de téléphone invalide.'; return null;
        }
        return $p;   // texte conservé tel quel : le zéro initial et le + ne sont jamais perdus
    }

    private static function email(mixed $raw, array &$errors): ?string
    {
        $m = self::trim($raw);
        $m = $m === null ? null : mb_strtolower($m);
        if ($m === null || $m === '' || mb_strlen($m) > 254 || filter_var($m, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Adresse e-mail invalide.'; return null;
        }
        return $m;
    }

    private static function id(mixed $raw, array $valid, string $field, bool $required, array &$errors): ?int
    {
        if ($raw === null) {
            if ($required) { $errors[$field] = 'Champ obligatoire.'; }
            return null;
        }
        if (!is_int($raw) || !in_array($raw, $valid, true)) { $errors[$field] = 'Valeur inconnue.'; return null; }
        return $raw;
    }

    private static function remark(mixed $raw, array &$errors): string
    {
        $r = self::trim($raw ?? '');
        if ($r === null || mb_strlen($r) > 1000) { $errors['remark'] = 'Remarque invalide (1000 caractères maximum).'; return ''; }
        return $r;
    }
}
```

À vous de compléter/adapter (messages, règles décidées avec le pilotage) et de **tester les limites** : 100 vs 101 caractères, `é` compté pour 1, date du jour acceptée, lendemain refusé, `+33 6 00 00 00 00` et `0600000000` acceptés, `abc` refusé.

> `FILTER_VALIDATE_EMAIL` refuse les adresses à partie locale non ASCII. Si la directrice l’exige, décidez-le explicitement (DECISIONS.md) au lieu de le découvrir en recette.

### Tests du validateur

Le style du dépôt est une fonction `check()` sans framework. Créez `tests/validator.php` :

```php
<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use Board\Registration\Validator;

$checks = 0;
function check(bool $ok, string $message): void { global $checks; $checks++; if (!$ok) { throw new RuntimeException($message); } }

$ids = ['schools' => [1, 2, 3, 4], 'current_classes' => [1, 2, 3], 'entry_levels' => [7, 8], 'specialties' => [5]];
$today = new DateTimeImmutable('2026-10-02', new DateTimeZone('Europe/Paris'));
$valid = ['last_name' => 'Démonstration', 'first_name' => 'Camille', 'birth_date' => '2008-04-12',
    'phone' => '+33 6 00 00 00 00', 'email' => 'Camille.Demo@Example.test',
    'school_id' => 3, 'entry_level_id' => 7];

$r = Validator::validate($valid, $ids, $today);
check($r['errors'] === [] && $r['values']['email'] === 'camille.demo@example.test', 'Fiche valide refusée ou mail non normalisé');
check($r['values']['current_class_id'] === null && $r['values']['remark'] === '', 'Facultatifs mal normalisés');

// Règle du contrat : chaque cas invalide nomme le champ fautif.
$cases = [   // [champ attendu en erreur, valeur envoyée]
    ['last_name',        ['last_name' => '   ']],
    ['birth_date',       ['birth_date' => '2026-02-30']],     // T04 : date inexistante
    ['birth_date',       ['birth_date' => '2026-10-03']],     // date future
    ['email',            ['email' => 'pas-un-mail']],
    ['phone',            ['phone' => 'abc']],
    ['school_id',        ['school_id' => '3']],               // T07 : chaîne
    ['entry_level_id',   ['entry_level_id' => 999]],          // ID inconnu
    ['specialty_id',     ['specialty_id' => [5]]],            // tableau
    ['current_class_id', ['current_class_id' => true]],       // booléen
    ['id',               ['id' => 1]],                        // champ interdit
];
foreach ($cases as [$field, $override]) {
    $res = Validator::validate([...$valid, ...$override], $ids, $today);
    check(isset($res['errors'][$field]) && $res['values'] === [], 'Cas invalide non détecté : ' . $field . ' = ' . json_encode($override));
}
check(Validator::validate([...$valid, 'first_name' => ['Camille']], $ids, $today)['errors'] !== [], 'Tableau accepté comme prénom');
echo "$checks vérifications du validateur réussies.\n";
```

Puis branchez-le : dans `composer.json`, `"test": "php tests/unit.php && php tests/validator.php"` (puis relancez `docker compose up -d --build` si l’image copie `composer.json`, ou utilisez `compose.dev.yaml`).

## 5. API-02 — Insertion, UUID et doublons

### Repository

Il **construit lui-même les colonnes**. `$db->insert('registrations', $request->getParsedBody())` est interdit : le client contrôlerait les colonnes.

```php
<?php
declare(strict_types=1);
namespace Board\Registration;
use Medoo\Medoo;

final class Repository
{
    public function __construct(private Medoo $db, private int $eventId) {}

    /** @return array<string,list<int>> */
    public function referenceIds(): array
    {
        $ids = [];
        foreach (['schools', 'current_classes', 'entry_levels', 'specialties'] as $table) {   // liste fermée
            $ids[$table] = array_map('intval', $this->db->select($table, 'id'));
        }
        return $ids;
    }

    /** @param array<string,mixed> $v valeurs déjà validées */
    public function insert(array $v): string
    {
        $reference = self::uuid();
        $this->db->insert('registrations', [
            'reference' => $reference, 'event_id' => $this->eventId,
            'last_name' => $v['last_name'], 'first_name' => $v['first_name'], 'birth_date' => $v['birth_date'],
            'phone' => $v['phone'], 'email' => $v['email'], 'current_class_id' => $v['current_class_id'],
            'school_id' => $v['school_id'], 'entry_level_id' => $v['entry_level_id'],
            'specialty_id' => $v['specialty_id'], 'remark' => $v['remark'],
            // registered_at : DEFAULT CURRENT_TIMESTAMP en base, horodatage serveur
        ]);
        return $reference;
    }

    private static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);   // version 4
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);   // variante RFC 4122
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
```

Les noms de colonnes viennent de [04 — Données](../04-donnees-merise.md) ; si le pôle données en choisit d’autres, **le dictionnaire fait foi** : alignez-vous, ne l’inverse pas.

### La route

Ordre des contrôles (T27, T26, T07, T03, T08) — chacun *avant* le suivant :

```php
$app->post('/api/registrations', function (Request $request) use ($db): Response {
    if (!filter_var(getenv('REGISTRATIONS_OPEN') ?: 'false', FILTER_VALIDATE_BOOLEAN)) {
        return Json::error('REGISTRATIONS_CLOSED', 'Les inscriptions ne sont pas ouvertes.', 503);
    }
    if (!str_starts_with(strtolower($request->getHeaderLine('Content-Type')), 'application/json')) {
        return Json::error('UNSUPPORTED_MEDIA_TYPE', 'Le format JSON est attendu.', 415);
    }
    $body = $request->getParsedBody();
    // JSON malformé, scalaire ou liste → null/liste ; {} reste un tableau vide → 422 avec tous les champs requis.
    if (!is_array($body) || ($body !== [] && array_is_list($body))) {
        return Json::error('INVALID_JSON', 'Corps de requête invalide.', 400);
    }
    $repo = new \Board\Registration\Repository($db(), (int)(getenv('EVENT_ID') ?: 1));
    $today = new DateTimeImmutable('today', new DateTimeZone('Europe/Paris'));
    ['values' => $values, 'errors' => $errors] = \Board\Registration\Validator::validate($body, $repo->referenceIds(), $today);
    if ($errors !== []) {
        return Json::error('VALIDATION_FAILED', 'Corrigez les champs indiqués.', 422, $errors);
    }
    try {
        $reference = $repo->insert($values);
    } catch (PDOException $e) {
        $state = $e->errorInfo[0] ?? '';           // SQLSTATE, jamais getMessage()
        if ($state === '23505') {
            return Json::error('DUPLICATE_REGISTRATION', 'Une fiche identique a déjà été enregistrée pour ce salon. L’équipe peut vérifier votre inscription.', 409);
        }
        if ($state === '23503') {                  // clé étrangère : référentiel supprimé entre-temps
            return Json::error('VALIDATION_FAILED', 'Corrigez les champs indiqués.', 422);
        }
        throw $e;                                  // → 500 neutre via le gestionnaire d'erreurs
    }
    return Json::send(['data' => ['reference' => $reference, 'message' => 'Votre visite est enregistrée.']], 201);
});
```

Points à vérifier par vous-mêmes (et à noter dans vos preuves) :

- **Medoo remonte bien une `PDOException`** avec `errorInfo[0] === '23505'` pour un doublon. Écrivez le test avant de faire confiance au code.
- Si le pôle données ajoute d’autres contraintes d’unicité, distinguez-les par le **nom de contrainte** (dans `errorInfo[2]`) au lieu de tout appeler « doublon ». Lisez ce message pour comparer, **ne le journalisez pas** : le détail PostgreSQL (`Key (…)=(…) already exists`) contient e-mail et noms.
- La CI exige que `POST /api/registrations` avec `{}` réponde **503** quand `REGISTRATIONS_OPEN=false` : le contrôle d’ouverture reste donc en premier.
- Ne modifiez jamais l’ordre 503 → 415 → 400 → 422 sans mettre le contrat à jour.

### Limitation applicative

Nginx limite à 10 requêtes/minute par IP (burst 10) *sur la route normale*. Si PHP est joignable autrement, cette protection saute. Une limite côté PHP ressemble à `LoginLimiter` : même table `login_rate_limits`, clé distincte (`hash_hmac('sha256', 'registration|' . $ip, APP_SECRET)`), seuil **large**. Au salon, des dizaines de visiteurs peuvent apparaître derrière la même IP (Wi-Fi, carrier-grade NAT) : testez avec l’infra avant de choisir un chiffre, et utilisez `REMOTE_ADDR`, jamais un `X-Forwarded-For` fourni par le client. Réponse : `429` avec `Retry-After`.

### Test de concurrence (T08)

Preuve attendue : 1 × 201, le reste en 409, une seule ligne. Sous Git Bash, avec `REGISTRATIONS_OPEN=true` et sur **une base de test** :

```bash
BODY='{"last_name":"Concurrent","first_name":"Test","birth_date":"2008-04-12","phone":"+33 6 00 00 00 00","email":"concurrent@example.test","school_id":3,"entry_level_id":7}'
seq 8 | xargs -P 8 -I{} curl -s -o /dev/null -w '%{http_code}\n' \
  -H 'Content-Type: application/json' -d "$BODY" http://localhost:8080/api/registrations | sort | uniq -c
# attendu : 1 × 201 et 7 × 409

docker compose exec db sh -c "psql -U \$POSTGRES_USER -d \$POSTGRES_DB -tAc \"SELECT count(*) FROM registrations WHERE email = 'concurrent@example.test'\""
# attendu : 1
```

Restez à 8 requêtes et attendez une minute avant de relancer : au-delà, vous testez la limite Nginx (429), pas le doublon. Nettoyez ensuite vos lignes de test.

## 6. API-03 — Liste et détail

### Paramètres

Les valeurs de la query string sont des **chaînes** (ou des tableaux : `?page[]=1`). Refusez tout ce qui n’est pas un entier positif dans la plage, par 422 :

```php
final class Filters
{
    /** @return array{0: array{page:int,per_page:int,school_id:?int,entry_level_id:?int}, 1: array<string,string>} */
    public static function fromQuery(array $q): array
    {
        $spec = ['page' => [1, 1, 1000000], 'per_page' => [20, 1, 100], 'school_id' => [null, 1, 2147483647], 'entry_level_id' => [null, 1, 2147483647]];   // max = entier PostgreSQL
        $out = []; $errors = [];
        foreach ($spec as $key => [$default, $min, $max]) {
            if (!array_key_exists($key, $q)) { $out[$key] = $default; continue; }
            $n = is_string($q[$key]) ? filter_var($q[$key], FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]) : false;
            if ($n === false) { $errors[$key] = 'Entier positif attendu.'; } else { $out[$key] = $n; }
        }
        return [$out, $errors];
    }
}
```

Conséquence à annoncer au front/back-office : pour « Toutes les écoles », **ne pas envoyer** `school_id=` vide, omettre le paramètre.

### Requête

Medoo suffit pour des requêtes simples ; dès qu’il y a jointures, filtres facultatifs et agrégats, une **requête SQL brute paramétrée** est plus lisible et plus facile à expliquer à la relecture. La règle : *les fragments SQL sont écrits par vous ; seules les valeurs viennent du client, et elles sont liées.*

```php
/** @return array{items: list<array>, total: int} */
public function page(array $f): array
{
    $where = 'r.event_id = :event_id'; $params = [':event_id' => $this->eventId];
    if ($f['school_id'] !== null)      { $where .= ' AND r.school_id = :school_id';           $params[':school_id'] = $f['school_id']; }
    if ($f['entry_level_id'] !== null) { $where .= ' AND r.entry_level_id = :entry_level_id'; $params[':entry_level_id'] = $f['entry_level_id']; }

    $total = (int)$this->db->query("SELECT count(*) FROM registrations r WHERE $where", $params)->fetchColumn();

    $params += [':limit' => $f['per_page'], ':offset' => ($f['page'] - 1) * $f['per_page']];
    $rows = $this->db->query(
        "SELECT r.id, r.last_name, r.first_name, s.label AS school, l.label AS entry_level, r.registered_at
         FROM registrations r
         JOIN schools s ON s.id = r.school_id
         JOIN entry_levels l ON l.id = r.entry_level_id
         WHERE $where
         ORDER BY r.registered_at DESC, r.id DESC
         LIMIT :limit OFFSET :offset", $params)->fetchAll(PDO::FETCH_ASSOC);

    return ['items' => array_map(self::present(...), $rows), 'total' => $total];
}

private static function present(array $r): array
{
    return ['id' => (int)$r['id'], 'last_name' => $r['last_name'], 'first_name' => $r['first_name'],
        'school' => $r['school'], 'entry_level' => $r['entry_level'],
        // timestamptz → ISO 8601 Europe/Paris, conforme au contrat
        'registered_at' => (new DateTimeImmutable($r['registered_at']))->setTimezone(new DateTimeZone('Europe/Paris'))->format(DATE_ATOM)];
}
```

Pourquoi `ORDER BY registered_at DESC, id DESC` et pas seulement la date : deux fiches peuvent avoir le même instant ; sans second critère, une fiche peut apparaître sur deux pages ou sur aucune (T15). Pagination dans la réponse :

```php
['page' => $f['page'], 'per_page' => $f['per_page'], 'total' => $total, 'pages' => (int)ceil($total / $f['per_page'])]
```

Une page au-delà de la dernière renvoie 200 et `items: []` (c’est un choix à documenter, pas une erreur de serveur).

### Détail

```php
$group->get('/registrations/{id:[0-9]+}', function (Request $req, Response $res, array $args) use ($db): Response {
    if (strlen($args['id']) > 18) { return Json::error('NOT_FOUND', 'Fiche introuvable.', 404); }   // évite un dépassement d'entier
    $row = $repo->find((int)$args['id']);    // WHERE r.id = :id AND r.event_id = :event_id
    return $row === null ? Json::error('NOT_FOUND', 'Fiche introuvable.', 404) : Json::send(['data' => $row]);
});
```

Le détail renvoie les dix champs, les libellés (`school`, `entry_level`, `current_class`, `specialty`) et la date de saisie ; **documentez le mapping** dans le contrat. Une fiche d’un autre salon répond 404, pas 403 (on ne confirme pas son existence).

## 7. API-04 — Une seule synthèse pour tout

Le total et les groupes ne se calculent jamais dans le front, ni séparément pour le CSV, le PDF et le mail. Une classe, une requête :

```php
final class SummaryService
{
    public static function build(Medoo $db, int $eventId, ?int $schoolId, ?int $levelId): array
    {
        $where = 'r.event_id = :event_id'; $params = [':event_id' => $eventId];
        if ($schoolId !== null) { $where .= ' AND r.school_id = :school_id';           $params[':school_id'] = $schoolId; }
        if ($levelId !== null)  { $where .= ' AND r.entry_level_id = :entry_level_id'; $params[':entry_level_id'] = $levelId; }
        $rows = $db->query(
            "SELECT s.id AS school_id, s.label AS school, l.id AS entry_level_id, l.label AS entry_level, count(*) AS count
             FROM registrations r JOIN schools s ON s.id = r.school_id JOIN entry_levels l ON l.id = r.entry_level_id
             WHERE $where GROUP BY s.id, s.label, l.id, l.label ORDER BY s.label, l.label", $params)->fetchAll(PDO::FETCH_ASSOC);
        $groups = array_map(fn($g) => [...$g, 'school_id' => (int)$g['school_id'], 'entry_level_id' => (int)$g['entry_level_id'], 'count' => (int)$g['count']], $rows);
        $event = $db->get('events', ['id [Int]', 'label'], ['id' => $eventId]);
        return [
            'event' => $event, 'filters' => ['school_id' => $schoolId, 'entry_level_id' => $levelId],
            'groups' => $groups,
            'total' => array_sum(array_column($groups, 'count')),     // somme du MÊME résultat
            'generated_at' => (new DateTimeImmutable('now', new DateTimeZone('Europe/Paris')))->format(DATE_ATOM),
        ];
    }
}
```

Le total est la somme des groupes retournés : il ne peut donc pas diverger des lignes affichées (T16). Cas `0 fiche` : `groups: []`, `total: 0`.

### Exports

`SummaryExport::csv($data['groups'])` et `SummaryExport::pdf($data['event']['label'], $data['groups'], $scope)` existent déjà et sont testés. Vous écrivez la route :

```php
$group->get('/exports/{format:csv|pdf}', function (Request $req, Response $res, array $args) use ($db): Response {
    [$f, $errors] = Filters::fromQuery($req->getQueryParams());      // seuls school_id / entry_level_id comptent ici
    if ($errors) { return Json::error('VALIDATION_FAILED', 'Filtres invalides.', 422, $errors); }
    $data = SummaryService::build($db(), (int)(getenv('EVENT_ID') ?: 1), $f['school_id'], $f['entry_level_id']);
    $csv = $args['format'] === 'csv';
    $content = $csv ? SummaryExport::csv($data['groups']) : SummaryExport::pdf($data['event']['label'], $data['groups'] /*, périmètre en clair */);
    $response = new \Slim\Psr7\Response(200);
    $response->getBody()->write($content);
    return $response
        ->withHeader('Content-Type', $csv ? 'text/csv; charset=utf-8' : 'application/pdf')
        ->withHeader('Content-Disposition', 'attachment; filename="recap-salon-' . $eventDate . '.' . $args['format'] . '"')
        ->withHeader('Cache-Control', 'no-store')->withHeader('X-Content-Type-Options', 'nosniff');
});
```

`$eventDate` vient de la table `events` (jamais d’une saisie). Les fichiers sont renvoyés directement, **pas écrits** dans un répertoire public. Une personne non connectée reçoit 401, y compris sur les téléchargements, puisque la route est dans le groupe.

### Envoi par mail

Squelette de `POST /api/admin/summary/email` (détails côté pôle exports, mais l’API et la sécurité vous reviennent) :

1. Valider le corps (`recipient` chaîne, `format ∈ {csv,pdf}`, `filters` entiers ou `null`) → **422**.
2. `SummaryMailer::assertRecipient()` : `InvalidArgumentException` → 422 ; `DomainException` (hors allowlist) → **403**.
3. Limiter les envois répétés (compteur comme pour le login, par session ou par IP).
4. Générer la pièce jointe **côté serveur** avec `SummaryService` ; aucun fichier ni chemin venant du client.
5. `SummaryMailer::send()` dans un `try` ; sur n’importe quelle `Throwable` → `error_log('board mail failure')` **sans message** et **503** générique. Pas de nom d’hôte ni d’identifiant SMTP dans la réponse.
6. Succès → `200 {data:{status:'accepted', message:'Le relais de messagerie a accepté l’envoi.'}}`. N’affirmez pas « reçu ».

Pour tester : faux relais ou relais de test uniquement ; aucune donnée nominative vers un compte personnel.

## 8. SEC-01 — Sécurité des routes

### Matrice à automatiser

À mettre dans `tests/smoke.php` ou un `tests/security.php` (sur le modèle de `request()`/`expect()` existants). Une route privée oubliée dans la matrice est une route non testée.

| Route | Anonyme | Connecté sans jeton CSRF | Connecté avec jeton |
|---|---|---|---|
| `GET /admin/registrations` | 401 | — | 200 |
| `GET /admin/registrations/{id}` | 401 | — | 200 / 404 |
| `GET /admin/summary` | 401 | — | 200 |
| `GET /admin/exports/csv` et `/pdf` | 401 | — | 200 + bon `Content-Type` |
| `POST /admin/summary/email` | 401 | 403 | 200 / 403 (destinataire) / 422 |
| `POST /auth/logout` | 401 | 403 | 200 |
| `POST /auth/login` | — | 403 | 200 / 401 / 429 |
| `POST /registrations` | 201 / 4xx (public) | — | — |

Cas supplémentaires utiles : après `logout`, l’ancienne session répond 401 ; l’identifiant de session change après `login` (`session_regenerate_id` est déjà dans le code, **vérifiez-le**) ; 31 minutes d’inactivité → 401 (simulez en réglant `$_SESSION['last_activity']`).

### Contrôles « manuels » à consigner

- **Cookie** : `Set-Cookie: board_session=…; HttpOnly; SameSite=Lax` (+ `Secure` quand `APP_URL` est en `https://`). À vérifier avec `curl -i http://localhost:8080/api/auth/session`.
- **Injection** : essayez `' OR 1=1 --` dans chaque champ texte et dans `school_id` ; aucun `500`, aucune ligne bizarre.
- **XSS stockée** (T10) : stockez `<script>alert(1)</script>` en remarque ; l’API le renvoie tel quel (JSON), l’échappement est fait à l’affichage (`textContent`) et dans le PDF (`htmlspecialchars`). Vérifiez avec le back-office.
- **Logs** (T26/fuite) : après une série de tests avec un mot-sentinelle (`SENTINELLE-XYZ`) dans les champs, `docker compose logs api | grep SENTINELLE` doit **ne rien renvoyer**.
- **Erreurs** : aucune réponse ne contient `SQLSTATE`, `Stack trace`, ni un chemin de fichier. Testez corps trop grand, JSON cassé, route inconnue, méthode non autorisée.
- **Réponses non JSON de Nginx** : un corps > 32 Ko (413) ou la limite de débit (429) sont produits par Nginx, probablement en HTML et sans `Retry-After`. Prévenez le front (il ne doit pas appeler `response.json()` sans garde) et l’infra (ajout éventuel de `Retry-After`).

### Le login fourni

Le contrat dit : *comprendre et vérifier, pas revendiquer comme écrit*. Points à vérifier et à noter dans votre fiche : `password_verify` avec un hash factice pour limiter la différence de temps entre compte connu/inconnu ; régénération de l’identifiant de session ; limiteur atomique. Un point à tester : `LoginLimiter` compte **chaque** tentative, succès compris — sur un réseau partagé, 20 connexions en 15 minutes peuvent bloquer l’équipe du salon. Mesurez et ajustez avec le coordinateur si besoin, sans désactiver la protection.

## 9. Tests : ce qu’il faut écrire

| Niveau | Fichier | Contenu | Base ? |
|---|---|---|---|
| Unitaire | `tests/validator.php` | Règles du contrat (section 4) | non |
| Intégration | `tests/registrations.php` | INSERT réel, FK, doublon, filtre, portée salon, agrégat | oui (base de test) |
| Sécurité | `tests/smoke.php` / `tests/security.php` | Matrice de la section 8 | oui |
| Concurrence | script bash (section 5) | 1 × 201 + N × 409 | pile complète |

Règles :

- Un test exprime **une règle du contrat**, pas une recopie du code. « `school_id: "3"` → 422 » est un bon test ; « `validate()` renvoie ce que renvoie `validate()` » non.
- Les tests d’intégration utilisent des données fictives marquées (`__test_xxx@example.test`) et **nettoient dans un `finally`** comme le fait `smoke.php`. Ne supprimez jamais « toutes les fiches ».
- Jamais la base du salon : utilisez le conteneur de développement.
- Branchez vos scripts dans `composer.json` (`test`, `smoke`) et dans la CI ([`.github/workflows/ci.yml`](../../.github/workflows/ci.yml) exécute déjà `composer test` et `composer smoke`) — prévenez le pôle QA/infra avant de modifier le fichier de CI.

## 10. Nouvelles variables d’environnement

Un `getenv('MA_VARIABLE')` ne marche **que** si la variable traverse trois fichiers :

1. `.env.example` (valeur de développement) et `.env.production.example` (valeur type) ;
2. `compose.yaml`, section `api.environment` (sinon le conteneur ne la reçoit pas) ;
3. `bin/check-config.php` si elle est obligatoire en production (il ne contrôle aujourd’hui que `APP_SECRET`, `DB_PASSWORD`, `APP_URL`, `APP_DEBUG`).

Puis `docker compose up -d --force-recreate api`. Aucune valeur secrète dans Git : donnez à l’infra le **nom** et une **valeur type**.

## 11. Git et PR

```bash
git switch main && git pull --ff-only
git switch -c feat/API-01-validation
# travail + tests
git add backend/src/Registration/Validator.php backend/tests/validator.php
git commit -m "API-01 : valider les champs d’inscription"
git push -u origin feat/API-01-validation
```

- Une branche par tâche (`feat/API-0x-…`), une PR courte avec l’ID, le [modèle de PR](../../.github/PULL_REQUEST_TEMPLATE.md) rempli, les tests réellement observés.
- Prévenez avant de toucher `src/app.php`. Pas de force-push sur `main`.
- Une migration appliquée ne se modifie plus : demandez une `004_…` au pôle données.
- Ne committez ni secret, ni export de vraies fiches, ni capture nominative.

## 12. Diagnostic rapide

| Symptôme | Cause probable | Vérification |
|---|---|---|
| 503 `REGISTRATIONS_CLOSED` | Ouverture désactivée (normal par défaut) | `REGISTRATIONS_OPEN=true` dans `.env`, puis `up -d --force-recreate api` |
| 501 `NOT_IMPLEMENTED` | Route pas encore remplacée | La tâche du backlog n’est pas branchée |
| 500 sur INSERT | Colonne ou table absente, migration 003 non appliquée | `docker compose exec api php bin/migrate.php` ; `docker compose logs api` |
| 500 avec `SQLSTATE[22003]` | Entier trop grand passé à une colonne `bigint`/`integer` | Plafonner la longueur de l’ID, valider l’intervalle |
| Doublon jamais détecté | Index unique absent, ou clé comparée sans `lower(trim(...))` alors que l’API normalise | Revoir l’index avec le pôle données |
| « Tout » est détecté comme doublon | `23505` traité sans distinguer les contraintes | Lire le nom de contrainte (sans le logguer) |
| 401 alors que connecté | Cookie non renvoyé | Client sans cookies, `credentials: same-origin` côté front |
| 403 CSRF | Jeton absent ou périmé | Rappeler `/auth/session` ; garder le nouveau jeton après login |
| 400 sur un JSON correct | Mauvais `Content-Type` ou corps vide | En-tête `application/json`, corps non vide |
| `smoke` casse après un refactor | `$db` renommé, `return $app` oublié, route admin sortie du groupe | Section 3 |
| Variable d’env introuvable | Absente de `compose.yaml` | Section 10 |
| Total ≠ somme des lignes affichées | Total calculé sur la page, ou avec d’autres filtres | Réutiliser `SummaryService` |
| `Permission denied` / `board-entrypoint: no such file` | Fins de ligne CRLF d’un script Windows | Voir [03 — Démarrage](../03-demarrage.md) |

## 13. Checklist avant de dire « terminé »

- [ ] La fonctionnalité fait ce que dit le contrat (codes HTTP et noms de champs compris).
- [ ] Un test normal, un test d’erreur, et pour une route privée un test **sans session**.
- [ ] Aucune saisie, aucun SQL, aucun secret dans les réponses ni dans les logs.
- [ ] Tout accès SQL utilise des paramètres liés ; aucune colonne ni `ORDER BY` ne vient du client.
- [ ] Le contrat (`docs/05`) et `DECISIONS.md` sont à jour si une réponse a changé.
- [ ] Relecture par le binôme ; PR avec l’ID ; preuve (sortie des tests) jointe.
- [ ] Les exemples 201/422/409 sont remis au front, la commande de tests à QA.
