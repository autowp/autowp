ALTER TABLE vehicle_vehicle_type RENAME item_vehicle_type;
ALTER TABLE item_vehicle_type CHANGE vehicle_id item_id int unsigned not null;