-- item_vehicle_type is keyed on (item_id, vehicle_type_id), so a lookup that starts from the
-- vehicle type - "which vehicles are of this type?" - has no index to use and scans the whole
-- table. The mosts menu asks exactly that, once per vehicle type, per page render, joined against
-- item_parent_cache to scope it to one brand; in production those calls ran for over two minutes
-- before the client gave up. The reverse index turns each of them into a lookup.
CREATE INDEX IF NOT EXISTS item_vehicle_type_vehicle_type_id_index ON item_vehicle_type (vehicle_type_id);
