package schema_test

import (
	"testing"

	"github.com/autowp/goautowp/schema"
	"github.com/stretchr/testify/require"
)

func TestNullAttributeTypeIDScanNil(t *testing.T) {
	t.Parallel()

	var value schema.NullAttributeTypeID

	err := value.Scan(nil)
	require.NoError(t, err)
	require.False(t, value.Valid)
	require.Equal(t, schema.AttrsAttributeTypeIDUnknown, value.AttributeTypeID)
}

func TestNullAttributeTypeIDScanValid(t *testing.T) {
	t.Parallel()

	var value schema.NullAttributeTypeID

	err := value.Scan(int64(schema.AttrsAttributeTypeIDString))
	require.NoError(t, err)
	require.True(t, value.Valid)
	require.Equal(t, schema.AttrsAttributeTypeIDString, value.AttributeTypeID)
}

func TestNullAttributeTypeIDScanUnsupportedType(t *testing.T) {
	t.Parallel()

	var value schema.NullAttributeTypeID

	err := value.Scan("not-an-int")
	require.Error(t, err)
}

func TestNullAttributeTypeIDValue(t *testing.T) {
	t.Parallel()

	invalid := schema.NullAttributeTypeID{Valid: false}

	value, err := invalid.Value()
	require.NoError(t, err)
	require.Nil(t, value)

	valid := schema.NullAttributeTypeID{AttributeTypeID: schema.AttrsAttributeTypeIDString, Valid: true}

	value, err = valid.Value()
	require.NoError(t, err)
	require.Equal(t, schema.AttrsAttributeTypeIDString, value)
}
