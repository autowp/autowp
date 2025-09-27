ALTER TABLE car_types_parents RENAME vehicle_type_parent;
ALTER TABLE car_types RENAME vehicle_type;
ALTER TABLE item
    CHANGE car_type_id _car_type_id int unsigned null,
    CHANGE car_type_inherit vehicle_type_inherit tinyint(1) default 0 not null;