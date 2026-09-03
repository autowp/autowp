-- Image licence phase 2: a fixed set of licences the uploader/moderator can attach to a picture
-- (part of the GDPR/DSA compliance backlog - the ToS already promises an "uploader-chosen public
-- licence at upload"), plus an optional source URL for provenance/attribution verification.
--
-- picture_license is a small reference table (not a bare application-level enum) so the FK
-- enforces referential integrity; row ids match the PictureLicense proto enum ordinals.
CREATE TABLE picture_license (
    id smallint PRIMARY KEY,
    name varchar(50) NOT NULL
);

INSERT INTO picture_license (id, name) VALUES
    (0, 'unknown'),
    (1, 'all_rights_reserved'),
    (2, 'cc0'),
    (3, 'cc_by'),
    (4, 'cc_by_sa'),
    (5, 'cc_by_nc'),
    (6, 'cc_by_nc_sa'),
    (7, 'cc_by_nd'),
    (8, 'cc_by_nc_nd'),
    (9, 'public_domain');

ALTER TABLE picture ADD COLUMN license_id smallint NOT NULL DEFAULT 0
    REFERENCES picture_license (id);
ALTER TABLE picture ADD COLUMN source_url varchar(500);
