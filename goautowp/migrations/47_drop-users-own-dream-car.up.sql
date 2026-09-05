-- Legacy MySQL-era profile fields, never exposed via the gRPC API or the current frontend.
ALTER TABLE users DROP COLUMN own_car, DROP COLUMN dream_car;
