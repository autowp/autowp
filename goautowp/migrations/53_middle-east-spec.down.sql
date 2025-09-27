DELETE FROM `spec` WHERE id = 62;

UPDATE spec SET parent_id=29 WHERE id IN (12, 45);

UPDATE spec SET name='JP-spec' WHERE id=4;