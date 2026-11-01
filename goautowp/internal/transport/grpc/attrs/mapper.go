package attrs

import (
	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/attrs"
	"github.com/autowp/goautowp/internal/schema"
)

func extractAttrValue(value attrs.Value) pb.AttrValueValue {
	return pb.AttrValueValue{
		Type:        extractAttrTypeID(value.Type),
		Valid:       value.Valid,
		FloatValue:  value.FloatValue,
		IntValue:    value.IntValue,
		BoolValue:   value.BoolValue,
		ListValue:   value.ListValue,
		StringValue: value.StringValue,
		IsEmpty:     value.IsEmpty,
	}
}

func extractAttrTypeID(in schema.AttrsAttributeTypeID) pb.AttrAttributeType_ID {
	switch in {
	case schema.AttrsAttributeTypeIDString:
		return pb.AttrAttributeType_STRING
	case schema.AttrsAttributeTypeIDInteger:
		return pb.AttrAttributeType_INTEGER
	case schema.AttrsAttributeTypeIDFloat:
		return pb.AttrAttributeType_FLOAT
	case schema.AttrsAttributeTypeIDText:
		return pb.AttrAttributeType_TEXT
	case schema.AttrsAttributeTypeIDBoolean:
		return pb.AttrAttributeType_BOOLEAN
	case schema.AttrsAttributeTypeIDList:
		return pb.AttrAttributeType_LIST
	case schema.AttrsAttributeTypeIDTree:
		return pb.AttrAttributeType_TREE
	case schema.AttrsAttributeTypeIDUnknown:
		return pb.AttrAttributeType_UNKNOWN
	}

	return pb.AttrAttributeType_UNKNOWN
}

func convertNullTypeID(typeID schema.NullAttributeTypeID) pb.AttrAttributeType_ID {
	if !typeID.Valid {
		return pb.AttrAttributeType_UNKNOWN
	}

	return convertTypeID(typeID.AttributeTypeID)
}

func convertTypeID(typeID schema.AttrsAttributeTypeID) pb.AttrAttributeType_ID {
	switch typeID {
	case schema.AttrsAttributeTypeIDUnknown:
		return pb.AttrAttributeType_UNKNOWN
	case schema.AttrsAttributeTypeIDString:
		return pb.AttrAttributeType_STRING
	case schema.AttrsAttributeTypeIDInteger:
		return pb.AttrAttributeType_INTEGER
	case schema.AttrsAttributeTypeIDFloat:
		return pb.AttrAttributeType_FLOAT
	case schema.AttrsAttributeTypeIDText:
		return pb.AttrAttributeType_TEXT
	case schema.AttrsAttributeTypeIDBoolean:
		return pb.AttrAttributeType_BOOLEAN
	case schema.AttrsAttributeTypeIDList:
		return pb.AttrAttributeType_LIST
	case schema.AttrsAttributeTypeIDTree:
		return pb.AttrAttributeType_TREE
	}

	return pb.AttrAttributeType_UNKNOWN
}

func convertAttribute(row *schema.AttrsAttributeRow) *pb.AttrAttribute {
	var parentID int64
	if row.ParentID.Valid {
		parentID = row.ParentID.Int64
	}

	var unitID int64
	if row.UnitID.Valid {
		unitID = row.UnitID.Int64
	}

	var description string
	if row.Description.Valid {
		description = row.Description.String
	}

	var precision int32
	if row.Precision.Valid {
		precision = row.Precision.Int32
	}

	return &pb.AttrAttribute{
		Id:          row.ID,
		Name:        row.Name,
		ParentId:    parentID,
		Description: description,
		TypeId:      convertNullTypeID(row.TypeID),
		UnitId:      unitID,
		IsMultiple:  row.Multiple,
		Precision:   precision,
	}
}
