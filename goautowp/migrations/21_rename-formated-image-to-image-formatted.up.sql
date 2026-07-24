ALTER TABLE formated_image RENAME TO image_formatted;
ALTER TABLE image_formatted RENAME COLUMN formated_image_id TO image_formatted_id;
