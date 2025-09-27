alter table pictures
    add column taken_year smallint default null,
    add column taken_month tinyint default null,
    add column taken_day tinyint default null;
