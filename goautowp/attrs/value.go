package attrs

import (
	"github.com/autowp/goautowp/schema"
)

type Value struct {
	Valid       bool
	IntValue    int32
	FloatValue  float64
	StringValue string
	BoolValue   bool
	ListValue   []int64
	Type        schema.AttrsAttributeTypeID
	IsEmpty     bool
}

func (s Value) Equals(val Value) bool {
	if !s.Valid || !val.Valid {
		return false
	}

	if s.Type != val.Type {
		return false
	}

	if s.IsEmpty && !val.IsEmpty || !s.IsEmpty && val.IsEmpty {
		return false
	} else if s.IsEmpty {
		return true
	}

	switch s.Type {
	case schema.AttrsAttributeTypeIDString, schema.AttrsAttributeTypeIDText:
		return s.StringValue == val.StringValue

	case schema.AttrsAttributeTypeIDInteger:
		return s.IntValue == val.IntValue

	case schema.AttrsAttributeTypeIDBoolean:
		return s.BoolValue == val.BoolValue

	case schema.AttrsAttributeTypeIDFloat:
		return s.FloatValue == val.FloatValue

	case schema.AttrsAttributeTypeIDList, schema.AttrsAttributeTypeIDTree:
		if len(s.ListValue) != len(val.ListValue) {
			return false
		}

		for i, listValue := range s.ListValue {
			if listValue != val.ListValue[i] {
				return false
			}
		}

	case schema.AttrsAttributeTypeIDUnknown:
	}

	return true
}
