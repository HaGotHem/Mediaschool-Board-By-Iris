-- Listes transcrites du formulaire Salon.pdf. Un identifiant stable est échangé par l'API.
INSERT INTO events (id, code, label, event_date) VALUES (1, 'STUDYRAMA-NICE-2026-10-03', 'Salon Studyrama — 3 octobre 2026', '2026-10-03');
SELECT setval(pg_get_serial_sequence('events','id'), (SELECT max(id) FROM events));
INSERT INTO schools VALUES (1,'ECS','ECS'),(2,'PSL','PSL'),(3,'IRIS','IRIS'),(4,'NSS','NSS');
INSERT INTO current_classes VALUES (1,'SECONDE','Seconde'),(2,'PREMIERE','Première'),(3,'TERMINALE','Terminale'),(4,'B1','B1'),(5,'B2','B2'),(6,'B3','B3'),(7,'M1_PLUS','M1 et Plus');
INSERT INTO entry_levels VALUES
(1,'BTS_COM','BTS communication'),(2,'B1','B1'),(3,'B2','B2'),(4,'B3','B3'),(5,'M1','M1'),(6,'M2','M2'),
(7,'IRIS_BTS1','Iris > BTS 1'),(8,'IRIS_BTS2','Iris > BTS 2'),(9,'IRIS_B3','Iris > B3'),(10,'IRIS_M1','Iris > M1'),(11,'IRIS_M2','Iris > M2');
INSERT INTO specialties VALUES
(1,'COM_EVENT','Com Event'),(2,'CREA_DIGIT','Crea Digit'),(3,'DA','DA'),
(4,'MANAGER_DEV','Manager du développement d’entreprise et commercial'),
(5,'IRIS_SLAM','Iris > SLAM'),(6,'IRIS_SISR','Iris > SISR'),(7,'IRIS_B3_AIS','Iris > B3 AIS'),(8,'IRIS_B3_CSD','Iris > B3 CSD'),
(9,'IRIS_M1_CYBER','Iris > M1 IT Cybersécurité'),(10,'IRIS_M1_DEV','Iris > M1 IT Dev'),(11,'IRIS_M2_CYBER','Iris M2 IT Cybersécurité');
