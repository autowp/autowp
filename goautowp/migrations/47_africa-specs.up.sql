INSERT INTO `spec` (id, name, short_name, parent_id)
VALUES (60, 'Africa LHD', 'Africa LHD', 29);

UPDATE spec SET parent_id=60 WHERE id IN (57, 49);
