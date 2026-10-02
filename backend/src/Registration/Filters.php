<?php
declare(strict_types=1);
namespace Board\Registration;

/** Filtres de lecture admin (liste, synthèse, exports), validés depuis la query string. */
final class Filters
{
    public const MAX_PER_PAGE = 100;

    private function __construct(
        public readonly ?int $schoolId,
        public readonly ?int $entryLevelId,
        public readonly int $page,
        public readonly int $perPage,
    ) {
    }

    /**
     * @param array<string,mixed> $query
     * @return array{filters: ?self, errors: array<string,string>}
     */
    public static function fromQuery(array $query): array
    {
        $errors = [];
        $read = static function (string $name, ?int $default, int $max = PHP_INT_MAX) use ($query, &$errors): ?int {
            if (!array_key_exists($name, $query)) {
                return $default;
            }
            $raw = $query[$name];
            // Refuse tableaux (page[]=1), chaînes vides, signes, zéros initiaux et décimales.
            if (!is_string($raw) || preg_match('/^[1-9][0-9]{0,8}$/', $raw) !== 1 || (int)$raw > $max) {
                $errors[$name] = $max === PHP_INT_MAX ? 'Entier positif attendu.' : "Entier entre 1 et $max attendu.";
                return null;
            }
            return (int)$raw;
        };

        $schoolId = $read('school_id', null);
        $entryLevelId = $read('entry_level_id', null);
        $page = $read('page', 1);
        $perPage = $read('per_page', 20, self::MAX_PER_PAGE);

        if ($errors !== []) {
            return ['filters' => null, 'errors' => $errors];
        }
        return ['filters' => new self($schoolId, $entryLevelId, (int)$page, (int)$perPage), 'errors' => []];
    }
}
