package query

const attrsZoneAttributesAlias = "aza"

func AppendAttrsZoneAttributesAlias(alias string) string {
	return alias + "_" + attrsZoneAttributesAlias
}
