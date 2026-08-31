-- Advisory author candidates for a freshly uploaded picture, derived from EXIF Artist/Copyright
-- and matched against persons already known as photo authors in the catalogue. Never sets an
-- author on its own: the uploader picks one in the upload grid (or the single candidate is
-- pre-applied). Only read for the owner's own inbox pictures; cleared by ON DELETE CASCADE.
CREATE TABLE picture_author_suggestion (
  picture_id integer      NOT NULL REFERENCES picture (id) ON DELETE CASCADE,
  item_id    integer      NOT NULL REFERENCES item (id) ON DELETE CASCADE,
  source     varchar(16)  NOT NULL,
  raw_value  varchar(255) NOT NULL DEFAULT '',
  created_at timestamptz  NOT NULL DEFAULT NOW(),
  PRIMARY KEY (picture_id, item_id)
);
