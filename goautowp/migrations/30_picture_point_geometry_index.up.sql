-- GetPicturePoints (map-grpc.go) needs a bounding-box query over picture.point at scale (far more
-- geotagged pictures than item_point's factories/museums), compared as geometry (planar) rather
-- than geography for the same reason item_point_point_geom exists - see
-- migrations/29_item_point_geometry_index.up.sql. Partial on point IS NOT NULL since most
-- pictures have no location, keeping the index small.
CREATE INDEX picture_point_geom ON picture USING GIST ((point::geometry)) WHERE point IS NOT NULL;
