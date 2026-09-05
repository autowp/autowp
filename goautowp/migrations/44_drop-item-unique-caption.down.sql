ALTER TABLE item ADD CONSTRAINT item_unique_caption
    UNIQUE (name, begin_year, body, end_year, begin_model_year, end_model_year, is_group, spec_id);
