-- 004_optional_appointment.sql
-- Le rendez-vous devient facultatif : quand tous les créneaux sont pris, l'inscription
-- est quand même enregistrée, sans date, créneau ni conseiller.
-- registrations_slot_uq reste valable : PostgreSQL considère les NULL comme distincts.

ALTER TABLE registrations
    ALTER COLUMN appointment_date DROP NOT NULL,
    ALTER COLUMN time_slot_id DROP NOT NULL,
    ALTER COLUMN advisor_id DROP NOT NULL,
    -- Soit les trois champs du rendez-vous, soit aucun
    ADD CONSTRAINT registrations_appointment_all_or_none CHECK (
        (appointment_date IS NULL AND time_slot_id IS NULL AND advisor_id IS NULL)
        OR (appointment_date IS NOT NULL AND time_slot_id IS NOT NULL AND advisor_id IS NOT NULL)
    );
