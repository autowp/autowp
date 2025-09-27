INSERT INTO `car_types` (id, parent_id, catname, name, position, name_rp) VALUES
(46,29,'singleseater','car-type/singleseater',3,'car-type-rp/singleseater');

INSERT INTO `car_types_parents` (id, parent_id, level) VALUES (46,46,1),(46,29,0);