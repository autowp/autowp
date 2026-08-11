-- GetPoints (map-grpc.go) filters item_point by comparing the point cast to geometry (planar),
-- not the geography column directly, to correctly handle bounding boxes wider than 180deg of
-- longitude and boxes panned past +-180deg into a repeated "world copy" - both routine at low
-- zoom. The existing GIST index on the geography column doesn't cover queries against that cast,
-- so add an expression index that does.
CREATE INDEX item_point_point_geom ON item_point USING GIST ((point::geometry));
