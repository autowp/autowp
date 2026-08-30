-- User-declared links to external profiles (social networks, photo portfolios, car
-- communities). One row per platform per user; platform is a small enum mirrored by
-- usercontacts.Platforms. `username` is the extracted/normalised handle, never a raw URL.
CREATE TABLE user_contact (
  user_id  integer     NOT NULL REFERENCES users (id) ON DELETE CASCADE,
  platform smallint    NOT NULL,
  username varchar(64) NOT NULL,
  PRIMARY KEY (user_id, platform)
);

-- Whether the user's contacts are shown to anonymous visitors. Off by default: a signed-in
-- member always sees them, an anonymous visitor only when the owner opts in.
ALTER TABLE users ADD COLUMN contacts_public boolean NOT NULL DEFAULT false;
