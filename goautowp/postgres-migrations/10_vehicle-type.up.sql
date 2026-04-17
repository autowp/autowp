create table vehicle_type
(
  id        int primary key,
  parent_id int null,
  catname   varchar(20)      not null unique,
  name      varchar(35)      not null unique,
  position  int not null,
  name_rp   varchar(50)      not null,
  constraint vehicle_type_unique_position unique (position, parent_id)
);

create index vehicle_type_parent_id on vehicle_type (parent_id);

ALTER TABLE vehicle_type DISABLE TRIGGER ALL;
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (1, 29, 'roadster', 'car-type/roadster', 1, 'car-type-rp/roadster');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (2, 29, 'spyder', 'car-type/spyder', 2, 'car-type-rp/spyder');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (3, null, 'cabriolet', 'car-type/cabriolet', 3, 'car-type-rp/cabriolet');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (4, 29, 'cabrio-coupe', 'car-type/cabrio-coupe', 4, 'car-type-rp/cabrio-coupe');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (5, 29, 'targa', 'car-type/targa', 5, 'car-type-rp/targa');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (6, 29, 'coupe', 'car-type/coupe', 8, 'car-type-rp/coupe');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (7, 29, 'sedan', 'car-type/sedan', 9, 'car-type-rp/sedan');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (8, 29, 'hatchback', 'car-type/hatchback', 13, 'car-type-rp/hatchback');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (9, null, 'crossover', 'car-type/crossover', 16, 'car-type-rp/crossover');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (10, null, 'universal', 'car-type/universal', 14, 'car-type-rp/universal');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (11, null, 'limousine', 'car-type/limousine', 21, 'car-type-rp/limousine');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (12, null, 'pickup', 'car-type/pickup', 20, 'car-type-rp/pickup');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (13, 29, 'caravan', 'car-type/caravan', 15, 'car-type-rp/caravan');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (14, null, 'offroad', 'car-type/offroad', 17, 'car-type-rp/offroad');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (15, null, 'minivan', 'car-type/minivan', 22, 'car-type-rp/minivan');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (16, null, 'van', 'car-type/van', 23, 'car-type-rp/van');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (17, null, 'truck', 'car-type/truck', 24, 'car-type-rp/truck');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (19, null, 'bus', 'car-type/bus', 25, 'car-type-rp/bus');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (20, 29, 'phaeton', 'car-type/phaeton', 7, 'car-type-rp/phaeton');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (21, 7, '4door-hardtop', 'car-type/4door-hardtop', 10, 'car-type-rp/4door-hardtop');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (22, 29, 'landau', 'car-type/landau', 6, 'car-type-rp/landau');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (25, 6, 'liftback-coupe', 'car-type/liftback-coupe', 26, 'car-type-rp/liftback-coupe');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (26, 7, 'liftback-sedan', 'car-type/liftback-sedan', 27, 'car-type-rp/liftback-sedan');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (27, 6, '2door-hardtop', 'car-type/2door-hardtop', 11, 'car-type-rp/2door-hardtop');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (28, 19, 'minibus', 'car-type/minibus', 0, 'car-type-rp/minibus');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (29, null, 'car', 'car-type/car', 1, 'car-type-rp/car');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (32, 19, 'multiplex-bus', 'car-type/multiplex-bus', 5, 'car-type-rp/multiplex-bus');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (33, 14, 'offroad-short', 'car-type/offroad-short', 77, 'car-type-rp/offroad-short');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (34, 29, 'brougham', 'car-type/brougham', 22, 'car-type-rp/brougham');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (36, 7, 'fastback-sedan', 'car-type/fastback-sedan', 50, 'car-type-rp/fastback-sedan');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (37, 6, 'fastback-coupe', 'car-type/fastback-coupe', 49, 'car-type-rp/fastback-coupe');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (38, 29, 'tonneau', 'car-type/tonneau', 43, 'car-type-rp/tonneau');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (39, 19, '2-floor-bus', 'car-type/2-floor-bus', 6, 'car-type-rp/2-floor-bus');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (40, 29, 'town-car', 'car-type/town-car', 70, 'car-type-rp/town-car');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (41, 29, 'barchetta', 'car-type/barchetta', 99, 'car-type-rp/barchetta');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (43, null, 'moto', 'car-type/moto', 100, 'car-type-rp/moto');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (44, null, 'tractor', 'car-type/tractor', 101, 'car-type-rp/tractor');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (45, null, 'tracked', 'car-type/tracked', 102, 'car-type-rp/tracked');
INSERT INTO vehicle_type (id, parent_id, catname, name, position, name_rp) VALUES (46, 29, 'singleseater', 'car-type/singleseater', 3, 'car-type-rp/singleseater');
ALTER TABLE vehicle_type ENABLE TRIGGER ALL;


create table vehicle_type_parent
(
  id        int not null,
  parent_id int not null,
  level     int not null,
  primary key (id, parent_id),
  constraint vehicle_type_parent_ibfk_1
    foreign key (id) references vehicle_type (id)
      on delete cascade,
  constraint vehicle_type_parent_ibfk_2
    foreign key (parent_id) references vehicle_type (id)
      on delete cascade
);

create index vehicle_type_parent_parent_id on vehicle_type_parent (parent_id);

INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (1, 1, 1);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (1, 29, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (2, 2, 1);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (2, 29, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (3, 3, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (4, 4, 1);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (4, 29, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (5, 5, 1);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (5, 29, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (6, 6, 1);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (6, 29, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (7, 7, 1);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (7, 29, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (8, 8, 1);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (8, 29, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (9, 9, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (10, 10, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (11, 11, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (12, 12, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (13, 13, 1);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (13, 29, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (14, 14, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (15, 15, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (16, 16, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (17, 17, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (19, 19, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (20, 20, 1);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (20, 29, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (21, 7, 1);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (21, 21, 2);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (21, 29, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (22, 22, 1);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (22, 29, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (25, 6, 1);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (25, 25, 2);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (25, 29, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (26, 7, 1);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (26, 26, 2);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (26, 29, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (27, 6, 1);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (27, 27, 2);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (27, 29, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (28, 19, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (28, 28, 1);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (29, 29, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (32, 19, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (32, 32, 1);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (33, 14, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (33, 33, 1);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (34, 29, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (34, 34, 1);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (36, 7, 1);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (37, 6, 1);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (38, 29, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (38, 38, 1);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (39, 19, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (39, 39, 1);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (40, 29, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (40, 40, 1);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (41, 29, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (41, 41, 1);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (43, 43, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (44, 44, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (45, 45, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (46, 29, 0);
INSERT INTO vehicle_type_parent (id, parent_id, level) VALUES (46, 46, 1);
