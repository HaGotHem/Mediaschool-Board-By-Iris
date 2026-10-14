-- 005_preferred_appointment.sql
-- Date et heure de rendez-vous choisies par le visiteur dans le formulaire (facultatives).
-- Distinctes du créneau attribué (appointment_date, time_slot_id, advisor_id).

ALTER TABLE registrations
    ADD COLUMN preferred_date date,
    ADD COLUMN preferred_time time,
    -- Soit la date et l'heure, soit aucune
    ADD CONSTRAINT registrations_preferred_all_or_none CHECK (
        (preferred_date IS NULL) = (preferred_time IS NULL)
    ),
    -- Du lundi (1) au vendredi (5)
    ADD CONSTRAINT registrations_preferred_weekday CHECK (
        preferred_date IS NULL OR EXTRACT(ISODOW FROM preferred_date) BETWEEN 1 AND 5
    );
