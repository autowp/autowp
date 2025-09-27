package goautowp

import (
	"github.com/autowp/goautowp/attrs"
	"github.com/autowp/goautowp/schema"
)

func extractAttrValue(value attrs.Value) AttrValueValue {
	return AttrValueValue{
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

func extractAttrTypeID(in schema.AttrsAttributeTypeID) AttrAttributeType_ID {
	switch in {
	case schema.AttrsAttributeTypeIDString:
		return AttrAttributeType_STRING
	case schema.AttrsAttributeTypeIDInteger:
		return AttrAttributeType_INTEGER
	case schema.AttrsAttributeTypeIDFloat:
		return AttrAttributeType_FLOAT
	case schema.AttrsAttributeTypeIDText:
		return AttrAttributeType_TEXT
	case schema.AttrsAttributeTypeIDBoolean:
		return AttrAttributeType_BOOLEAN
	case schema.AttrsAttributeTypeIDList:
		return AttrAttributeType_LIST
	case schema.AttrsAttributeTypeIDTree:
		return AttrAttributeType_TREE
	case schema.AttrsAttributeTypeIDUnknown:
		return AttrAttributeType_UNKNOWN
	}

	return AttrAttributeType_UNKNOWN
}
