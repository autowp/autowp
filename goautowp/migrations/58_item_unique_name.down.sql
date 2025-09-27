ALTER TABLE item
    DROP INDEX caption,
    ADD UNIQUE INDEX caption (name, begin_year, body, end_year, begin_model_year, end_model_year, is_group);
