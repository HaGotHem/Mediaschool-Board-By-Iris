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
