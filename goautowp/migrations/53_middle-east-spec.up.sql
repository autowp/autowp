UPDATE spec SET name='Japan' WHERE id=4;

INSERT INTO `spec` (id, name, short_name, parent_id)
VALUES (62, 'Middle East', 'Middle East', 29);

UPDATE spec SET parent_id=62 WHERE id IN (12, 45);
