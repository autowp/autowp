ALTER TABLE vehicle_type_parent RENAME car_types_parents;
ALTER TABLE vehicle_type RENAME car_types;
ALTER TABLE item
    CHANGE _car_type_id car_type_id int unsigned null,
    CHANGE vehicle_type_inherit car_type_inherit tinyint(1) default 0 not null;
