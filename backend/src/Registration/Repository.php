<?php
declare(strict_types=1);
namespace Board\Registration;

use Medoo\Medoo;
use PDO;
use PDOException;

/** Accès base de l'inscription. Ne reçoit que des valeurs déjà validées. */
final class Repository
{
    private const SLOT_ATTEMPTS = 5;

    public function __construct(private Medoo $db, private int $eventId)
    {
    }

    /** @return array<string,list<int>> */
    public function referenceIds(): array
    {
        $ids = [];
        foreach (['schools', 'current_classes', 'entry_levels', 'specialties'] as $table) { // liste fermée
            $ids[$table] = array_map('intval', $this->db->select($table, 'id'));
        }
        return $ids;
    }

    /**
     * Crée le visiteur (ou le retrouve par e-mail) puis l'inscription, avec le premier créneau libre.
     *
     * @param array<string,mixed> $v valeurs validées
     * @return array{id:int, appointment_date:string, time_slot:string}|null null si plus aucun créneau
     * @throws DuplicateRegistrationException visiteur déjà inscrit à ce salon
     */
    public function create(array $v): ?array
    {
        $pdo = $this->db->pdo;
        $pdo->beginTransaction();
        try {
            $visitorId = $this->visitorId($pdo, $v);
            $registration = $this->insertRegistration($pdo, $visitorId, $v);
            if ($registration === null) {
                $pdo->rollBack();
                return null;
            }
            $pdo->commit();
            return $registration;
        } catch (PDOException $e) {
            $pdo->rollBack();
            // SQLSTATE et nom de contrainte seulement : le détail PostgreSQL contient les saisies.
            if (($e->errorInfo[0] ?? '') === '23505' && str_contains((string)($e->errorInfo[2] ?? ''), 'registrations_visitor_event_uq')) {
                throw new DuplicateRegistrationException('duplicate');
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Liste paginée du salon actif. Pas de téléphone, e-mail ni date de naissance (réservés au détail).
     *
     * @return array{items: list<array<string,mixed>>, pagination: array{page:int, per_page:int, total:int, pages:int}}
     */
    public function list(Filters $filters): array
    {
        [$where, $params] = $this->where($filters);

        $pdo = $this->db->pdo;
        // Total calculé avec exactement les mêmes filtres que la page.
        $count = $pdo->prepare("SELECT count(*) FROM registrations r WHERE $where");
        $count->execute($params);
        $total = (int)$count->fetchColumn();

        // Tri serveur fixe ; id en second critère pour une pagination stable.
        $select = $pdo->prepare(
            "SELECT r.id, v.last_name, v.first_name, s.label AS school, l.label AS entry_level, r.created_at
             FROM registrations r
             JOIN visitors v ON v.id = r.visitor_id
             JOIN schools s ON s.id = r.school_id
             JOIN entry_levels l ON l.id = r.entry_level_id
             WHERE $where
             ORDER BY r.created_at DESC, r.id DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $name => $value) {
            $select->bindValue($name, $value, PDO::PARAM_INT);
        }
        $select->bindValue(':limit', $filters->perPage, PDO::PARAM_INT);
        $select->bindValue(':offset', ($filters->page - 1) * $filters->perPage, PDO::PARAM_INT);
        $select->execute();

        $paris = new \DateTimeZone('Europe/Paris');
        $items = array_map(static fn(array $row): array => [
            'id' => (int)$row['id'],
            'last_name' => $row['last_name'],
            'first_name' => $row['first_name'],
            'school' => $row['school'],
            'entry_level' => $row['entry_level'],
            'registered_at' => (new \DateTimeImmutable($row['created_at']))->setTimezone($paris)->format(DATE_ATOM),
        ], $select->fetchAll(PDO::FETCH_ASSOC));

        return ['items' => $items, 'pagination' => [
            'page' => $filters->page,
            'per_page' => $filters->perPage,
            'total' => $total,
            'pages' => max(1, (int)ceil($total / $filters->perPage)),
        ]];
    }

    /**
     * Synthèse du salon actif par école et niveau. Seule source pour le JSON, les exports et le mail :
     * le total est la somme des groupes du même résultat.
     *
     * @return array{groups: list<array{school_id:int, school:string, entry_level_id:int, entry_level:string, count:int}>, total:int}
     */
    public function summary(Filters $filters): array
    {
        [$where, $params] = $this->where($filters);
        $select = $this->db->pdo->prepare(
            "SELECT s.id AS school_id, s.label AS school, l.id AS entry_level_id, l.label AS entry_level, count(*) AS count
             FROM registrations r
             JOIN schools s ON s.id = r.school_id
             JOIN entry_levels l ON l.id = r.entry_level_id
             WHERE $where
             GROUP BY s.id, s.label, l.id, l.label
             ORDER BY s.label, l.label"
        );
        $select->execute($params);

        $groups = array_map(static fn(array $row): array => [
            'school_id' => (int)$row['school_id'],
            'school' => $row['school'],
            'entry_level_id' => (int)$row['entry_level_id'],
            'entry_level' => $row['entry_level'],
            'count' => (int)$row['count'],
        ], $select->fetchAll(PDO::FETCH_ASSOC));

        return ['groups' => $groups, 'total' => array_sum(array_column($groups, 'count'))];
    }

    /**
     * Périmètre commun à la liste et à la synthèse : salon actif + filtres école / niveau.
     *
     * @return array{0:string, 1:array<string,int>}
     */
    private function where(Filters $filters): array
    {
        $where = 'r.event_id = :event_id';
        $params = [':event_id' => $this->eventId];
        if ($filters->schoolId !== null) {
            $where .= ' AND r.school_id = :school_id';
            $params[':school_id'] = $filters->schoolId;
        }
        if ($filters->entryLevelId !== null) {
            $where .= ' AND r.entry_level_id = :entry_level_id';
            $params[':entry_level_id'] = $filters->entryLevelId;
        }
        return [$where, $params];
    }

    /** @param array<string,mixed> $v */
    private function visitorId(PDO $pdo, array $v): int
    {
        $insert = $pdo->prepare(
            'INSERT INTO visitors (last_name, first_name, birth_date, phone, email)
             VALUES (:last_name, :first_name, :birth_date, :phone, :email)
             ON CONFLICT (email) DO NOTHING RETURNING id'
        );
        $insert->execute([
            ':last_name' => $v['last_name'], ':first_name' => $v['first_name'], ':birth_date' => $v['birth_date'],
            ':phone' => $v['phone'], ':email' => $v['email'],
        ]);
        $id = $insert->fetchColumn();
        if ($id !== false) {
            return (int)$id;
        }
        // E-mail déjà connu : on réutilise la fiche visiteur sans la modifier. L'unicité
        // (visiteur, salon) de registrations tranche ensuite le doublon.
        $select = $pdo->prepare('SELECT id FROM visitors WHERE email = :email');
        $select->execute([':email' => $v['email']]);
        return (int)$select->fetchColumn();
    }

    /**
     * @param array<string,mixed> $v
     * @return array{id:int, appointment_date:string, time_slot:string}|null
     */
    private function insertRegistration(PDO $pdo, int $visitorId, array $v): ?array
    {
        $pick = $pdo->prepare(
            'SELECT appointment_date, time_slot_id, time_slot_label, advisor_id
             FROM available_slots WHERE event_id = :event_id
             ORDER BY time_slot_id, advisor_id LIMIT 1'
        );
        $insert = $pdo->prepare(
            'INSERT INTO registrations
                (visitor_id, event_id, current_class_id, school_id, entry_level_id, specialty_id, remark,
                 appointment_date, time_slot_id, advisor_id)
             VALUES (:visitor_id, :event_id, :current_class_id, :school_id, :entry_level_id, :specialty_id, :remark,
                 :appointment_date, :time_slot_id, :advisor_id)
             ON CONFLICT ON CONSTRAINT registrations_slot_uq DO NOTHING RETURNING id'
        );
        // Si un autre visiteur prend le même créneau en même temps, le DO NOTHING ne renvoie rien :
        // on relit les créneaux libres et on réessaie.
        for ($attempt = 0; $attempt < self::SLOT_ATTEMPTS; $attempt++) {
            $pick->execute([':event_id' => $this->eventId]);
            $slot = $pick->fetch(PDO::FETCH_ASSOC);
            if ($slot === false) {
                return null;
            }
            $insert->execute([
                ':visitor_id' => $visitorId, ':event_id' => $this->eventId,
                ':current_class_id' => $v['current_class_id'], ':school_id' => $v['school_id'],
                ':entry_level_id' => $v['entry_level_id'], ':specialty_id' => $v['specialty_id'], ':remark' => $v['remark'],
                ':appointment_date' => $slot['appointment_date'], ':time_slot_id' => $slot['time_slot_id'], ':advisor_id' => $slot['advisor_id'],
            ]);
            $id = $insert->fetchColumn();
            if ($id !== false) {
                return ['id' => (int)$id, 'appointment_date' => $slot['appointment_date'], 'time_slot' => $slot['time_slot_label']];
            }
        }
        return null;
    }
}
