create table spec
(
  id         int primary key,
  name       varchar(50)  not null unique,
  short_name varchar(15)  not null unique,
  parent_id  int null
);

create index spec_parent_id on spec (parent_id);

INSERT INTO spec (id, name, short_name, parent_id) VALUES (1, 'United States & Canada', 'North America', 36);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (2, 'United Kingdom & Ireland', 'UK-spec', 35);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (3, 'Australia', 'Australia', 35);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (4, 'Japan', 'Japan', 31);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (5, 'Brazil', 'Brazil', 20);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (6, 'Europe except United Kingdom & Ireland', 'EU-spec', 29);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (7, 'South Africa', 'South Africa', 35);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (8, 'Russian Federation', 'Russia', 15);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (9, 'China', 'China', 29);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (10, 'Mexico', 'Mexico', 29);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (11, 'Argentina', 'Argentina', 20);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (12, 'United Arab Emirates', 'UAE', 62);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (13, 'Finland', 'Finland', 6);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (14, 'India', 'India', 31);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (15, 'CIS', 'CIS', 6);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (16, 'Canada', 'Canada', 1);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (17, 'Hong Kong', 'Hong Kong', 31);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (18, 'Taiwan', 'Taiwan', 29);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (19, 'Malaysia', 'Malaysia', 31);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (20, 'Latin America', 'Latam', 29);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (21, 'South Korea', 'South Korea', 29);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (22, 'Germany', 'Germany', 6);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (23, 'Netherlands', 'Netherlands', 6);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (24, 'Iran', 'Iran', 29);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (25, 'New Zealand', 'New Zealand', 35);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (26, 'Thailand', 'Thailand', 31);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (27, 'Spain', 'Spain', 6);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (28, 'Philippines', 'Philippines', 29);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (29, 'Worldwide except US and RHD countries', 'Worldwide', 36);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (30, 'France', 'France', 6);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (31, 'Asian RHD', 'Asia RHD', 35);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (32, 'Singapore', 'Singapore', 31);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (33, 'Switzerland', 'Switzerland', 6);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (34, 'United States of America', 'US-spec', 1);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (35, 'Right-hand drive countries', 'RHD', null);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (36, 'Left-hand drive countries', 'LHD', null);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (37, 'Ireland', 'Ireland', 2);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (38, 'Luxembourg', 'Luxembourg', 6);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (39, 'Italy', 'Italy', 6);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (40, 'Belgium', 'Belgium', 6);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (41, 'Poland', 'Poland', 6);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (42, 'Hungary', 'Hungary', 6);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (43, 'Greece', 'Greece', 6);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (44, 'Pakistan', 'Pakistan', 31);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (45, 'Oman', 'Oman', 62);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (46, 'Turkey', 'Turkey', 29);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (47, 'Norway', 'Norway', 6);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (48, 'Sweden', 'Sweden', 6);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (49, 'Ethiopia', 'Ethiopia', 60);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (50, 'Denmark', 'Denmark', 6);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (51, 'USSR', 'USSR', 6);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (52, 'Ecuador', 'Ecuador', 20);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (53, 'Indonesia', 'Indonesia', 31);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (54, 'Chile', 'Chile', 20);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (55, 'Portugal', 'Portugal', 6);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (56, 'Vietnam', 'Vietnam', 29);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (57, 'Egypt', 'Egypt', 60);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (58, 'Czech Republic', 'Czech', 6);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (59, 'Austria', 'Austria', 6);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (60, 'Africa LHD', 'Africa LHD', 29);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (61, 'Ghana', 'Ghana', 60);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (62, 'Middle East', 'Middle East', 29);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (63, 'Kuwait', 'Kuwait', 62);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (64, 'Colombia', 'Colombia', 20);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (65, 'Peru', 'Peru', 20);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (66, 'Uruguay', 'Uruguay', 20);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (67, 'Paraguay', 'Paraguay', 20);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (68, 'Slovakia', 'Slovakia', 6);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (69, 'Romania', 'Romania', 6);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (70, 'Estonia', 'Estonia', 6);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (71, 'Nigeria', 'Nigeria', 60);
INSERT INTO spec (id, name, short_name, parent_id) VALUES (72, 'Saudi Arabia', 'Saudi Arabia', 62);

alter table spec add constraint spec_ibfk_1 foreign key (parent_id) references spec (id);
