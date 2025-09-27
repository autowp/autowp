ALTER TABLE item
    ADD COLUMN begin_model_year_fraction varchar(1) default null,
    ADD COLUMN end_model_year_fraction varchar(1) default null;