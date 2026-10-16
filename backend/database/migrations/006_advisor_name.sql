-- 006_advisor_name.sql
-- Nom du conseiller saisi librement par l'admin (facultatif).
-- Prioritaire à l'affichage sur le conseiller du créneau attribué (advisor_id).

ALTER TABLE registrations
    ADD COLUMN advisor_name varchar(100);
