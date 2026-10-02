<?php
declare(strict_types=1);
namespace Board\Registration;

/**
 * Validation pure d'une inscription : tableau → valeurs normalisées + erreurs.
 * Aucun accès base, aucun HTTP : testable sans serveur.
 */
final class Validator
{
    private const FORBIDDEN = ['id', 'event_id', 'visitor_id', 'reference', 'created_at', 'registered_at', 'appointment_date', 'time_slot_id', 'advisor_id', 'admin'];

    /**
     * @param array<string,mixed> $body
     * @param array<string,list<int>> $ids schools, current_classes, entry_levels, specialties
     * @return array{values: array<string,mixed>, errors: array<string,string>}
     */
    public static function validate(array $body, array $ids, \DateTimeImmutable $today): array
    {
        $errors = [];
        foreach (self::FORBIDDEN as $key) {
            if (array_key_exists($key, $body)) {
                $errors[$key] = 'Champ non autorisé.';
            }
        }
        $values = [
            'last_name' => self::name($body['last_name'] ?? null, 'last_name', 'Nom', $errors),
            'first_name' => self::name($body['first_name'] ?? null, 'first_name', 'Prénom', $errors),
            'birth_date' => self::birthDate($body['birth_date'] ?? null, $today, $errors),
            'phone' => self::phone($body['phone'] ?? null, $errors),
            'email' => self::email($body['email'] ?? null, $errors),
            'current_class_id' => self::id($body['current_class_id'] ?? null, $ids['current_classes'], 'current_class_id', false, $errors),
            'school_id' => self::id($body['school_id'] ?? null, $ids['schools'], 'school_id', true, $errors),
            'entry_level_id' => self::id($body['entry_level_id'] ?? null, $ids['entry_levels'], 'entry_level_id', true, $errors),
            'specialty_id' => self::id($body['specialty_id'] ?? null, $ids['specialties'], 'specialty_id', false, $errors),
            'remark' => self::remark($body['remark'] ?? null, $errors),
        ];
        return ['values' => $errors === [] ? $values : [], 'errors' => $errors];
    }

    /** Retourne null si ce n'est pas une chaîne UTF-8 valide. */
    private static function trim(mixed $value): ?string
    {
        return is_string($value) ? preg_replace('/^[\s\p{Z}\p{Cc}]+|[\s\p{Z}\p{Cc}]+$/u', '', $value) : null;
    }

    private static function name(mixed $raw, string $field, string $label, array &$errors): ?string
    {
        $s = self::trim($raw);
        if ($s === null || $s === '') {
            $errors[$field] = "$label obligatoire.";
            return null;
        }
        if (mb_strlen($s) > 100 || preg_match('/\p{Cc}/u', $s)) {
            $errors[$field] = "$label invalide.";
            return null;
        }
        return $s;
    }

    private static function birthDate(mixed $raw, \DateTimeImmutable $today, array &$errors): ?string
    {
        // '!' remet l'heure à 00:00 ; l'aller-retour format() refuse 2026-02-30 (qui deviendrait le 2 mars).
        $d = is_string($raw) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $raw, $today->getTimezone()) : false;
        if ($d === false || $d->format('Y-m-d') !== $raw || $d > $today || (int)$d->format('Y') < 1900) {
            $errors['birth_date'] = 'Date de naissance invalide.';
            return null;
        }
        return $raw;
    }

    private static function phone(mixed $raw, array &$errors): ?string
    {
        $p = self::trim($raw);
        $digits = $p === null ? '' : (string)preg_replace('/\D/', '', $p);
        if ($p === null || mb_strlen($p) > 30 || !preg_match('/^\+?[0-9 .()\-]+$/', $p) || strlen($digits) < 8 || strlen($digits) > 15) {
            $errors['phone'] = 'Numéro de téléphone invalide.';
            return null;
        }
        return $p; // conservé tel quel : le zéro initial et le + ne sont jamais perdus
    }

    private static function email(mixed $raw, array &$errors): ?string
    {
        $m = self::trim($raw);
        $m = $m === null ? null : mb_strtolower($m);
        if ($m === null || $m === '' || mb_strlen($m) > 254 || filter_var($m, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Adresse e-mail invalide.';
            return null;
        }
        return $m;
    }

    private static function id(mixed $raw, array $valid, string $field, bool $required, array &$errors): ?int
    {
        if ($raw === null) {
            if ($required) {
                $errors[$field] = 'Champ obligatoire.';
            }
            return null;
        }
        if (!is_int($raw) || !in_array($raw, $valid, true)) {
            $errors[$field] = 'Valeur inconnue.';
            return null;
        }
        return $raw;
    }

    private static function remark(mixed $raw, array &$errors): string
    {
        $r = self::trim($raw ?? '');
        if ($r === null || mb_strlen($r) > 1000) {
            $errors['remark'] = 'Remarque invalide (1000 caractères maximum).';
            return '';
        }
        return $r;
    }
}
