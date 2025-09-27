package goautowp

import (
	"context"
	"fmt"
	"math/rand"
	"slices"
	"sync"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/schema"
	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/metadata"
	"google.golang.org/protobuf/types/known/emptypb"
	"google.golang.org/protobuf/types/known/fieldmaskpb"
)

const (
	floatAttributeID        = schema.TurningDiameterAttr
	intAttributeID          = schema.LengthAttr
	stringAttributeID       = schema.FrontSuspensionTypeAttr
	boolAttributeID         = schema.SpeedLimiterAttr
	listAttributeID         = schema.EnginePlacementPlacementAttr
	treeAttributeID         = schema.FuelSupplySystemAttr
	treeMultipleAttributeID = schema.FuelTypeAttr
)

const (
	floatEmptyAttributeID  = schema.EngineCylinderDiameter
	intEmptyAttributeID    = schema.WidthAttr
	stringEmptyAttributeID = schema.RearSuspensionType
	boolEmptyAttributeID   = schema.ABSAttr
	listEmptyAttributeID   = schema.EnginePlacementOrientationAttr
	treeEmptyAttributeID   = schema.DriveUnitAttr
)

func TestGetUnits(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	client := NewAttrsClient(conn)

	_, err := client.GetUnits(
		ctx,
		&emptypb.Empty{},
	)
	require.NoError(t, err)
}

func TestGetZones(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := NewAttrsClient(conn)

	_, err := client.GetZones(ctx, &emptypb.Empty{})
	require.NoError(t, err)
}

func TestGetAttributeTypes(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := NewAttrsClient(conn)

	_, err := client.GetAttributeTypes(ctx, &emptypb.Empty{})
	require.NoError(t, err)
}

func TestGetAttributes(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, "frontend", "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := NewAttrsClient(conn)

	_, err = client.GetAttributes(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&AttrAttributesRequest{ZoneId: 1},
	)
	require.NoError(t, err)
}

func TestGetZoneAttributes(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := NewAttrsClient(conn)

	_, err := client.GetZoneAttributes(ctx, &AttrZoneAttributesRequest{ZoneId: 1})
	require.NoError(t, err)
}

func TestGetListOptions(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, "frontend", "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := NewAttrsClient(conn)

	_, err = client.GetListOptions(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&AttrListOptionsRequest{AttributeId: 1},
	)
	require.NoError(t, err)
}

func requireUserValues(t *testing.T, userValues *AttrUserValuesResponse) {
	t.Helper()

	var (
		floatFound        bool
		intFound          bool
		stringFound       bool
		boolFound         bool
		listFound         bool
		treeFound         bool
		treeMultipleFound bool
	)

	require.NotEmpty(t, userValues.GetItems())

	for _, val := range userValues.GetItems() { //nolint:dupl
		require.True(t, val.GetValue().GetValid())
		require.False(t, val.GetValue().GetIsEmpty())

		switch val.GetAttributeId() {
		case floatAttributeID:
			floatFound = true

			require.InDelta(t, 7.091, val.GetValue().GetFloatValue(), 0.01)
			require.Equal(t, "7.1", val.GetValueText())
		case intAttributeID:
			intFound = true

			require.Equal(t, int32(6), val.GetValue().GetIntValue())
		case stringAttributeID:
			stringFound = true

			require.Equal(t, "test", val.GetValue().GetStringValue())
		case boolAttributeID:
			boolFound = true

			require.True(t, val.GetValue().GetBoolValue())
		case listAttributeID:
			listFound = true

			require.Equal(t, []int64{1}, val.GetValue().GetListValue())
		case treeAttributeID:
			treeFound = true

			require.Equal(t, []int64{25}, val.GetValue().GetListValue())
		case treeMultipleAttributeID:
			treeMultipleFound = true

			require.Equal(t, []int64{28, 29}, val.GetValue().GetListValue())
		}
	}

	require.True(t, floatFound)
	require.True(t, intFound)
	require.True(t, stringFound)
	require.True(t, boolFound)
	require.True(t, listFound)
	require.True(t, treeFound)
	require.True(t, treeMultipleFound)
}

func requireValues(t *testing.T, values *AttrValuesResponse) {
	t.Helper()

	require.NotEmpty(t, values.GetItems())

	var (
		floatFound        bool
		intFound          bool
		stringFound       bool
		boolFound         bool
		listFound         bool
		treeFound         bool
		treeMultipleFound bool
	)

	for _, val := range values.GetItems() { //nolint:dupl
		require.True(t, val.GetValue().GetValid())
		require.False(t, val.GetValue().GetIsEmpty())

		switch val.GetAttributeId() {
		case floatAttributeID:
			floatFound = true

			require.InDelta(t, 7.091, val.GetValue().GetFloatValue(), 0.01)
			require.Equal(t, "7.1", val.GetValueText())
		case intAttributeID:
			intFound = true

			require.Equal(t, int32(6), val.GetValue().GetIntValue())
		case stringAttributeID:
			stringFound = true

			require.Equal(t, "test", val.GetValue().GetStringValue())
		case boolAttributeID:
			boolFound = true

			require.True(t, val.GetValue().GetBoolValue())
		case listAttributeID:
			listFound = true

			require.Equal(t, []int64{1}, val.GetValue().GetListValue())
		case treeAttributeID:
			treeFound = true

			require.Equal(t, []int64{25}, val.GetValue().GetListValue())
		case treeMultipleAttributeID:
			treeMultipleFound = true

			require.Equal(t, []int64{28, 29}, val.GetValue().GetListValue())
		}
	}

	require.True(t, floatFound)
	require.True(t, intFound)
	require.True(t, stringFound)
	require.True(t, boolFound)
	require.True(t, listFound)
	require.True(t, treeFound)
	require.True(t, treeMultipleFound)
}

func TestGetValues(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, "frontend", "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := NewAttrsClient(conn)

	itemID := createItem(t, conn, cnt, &APIItem{
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
		Name:       "TestGetValues",
	})

	values, err := client.GetValues(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&AttrValuesRequest{
			ItemId:   itemID,
			Language: "en",
		},
	)
	require.NoError(t, err)
	require.Empty(t, values.GetItems())

	_, err = client.SetUserValues(
		metadata.AppendToOutgoingContext(
			t.Context(),
			authorizationHeader,
			bearerPrefix+token.AccessToken,
		),
		&AttrSetUserValuesRequest{
			Items: []*AttrUserValue{
				{
					AttributeId: floatAttributeID,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 7.091,
					},
				},
				{
					AttributeId: intAttributeID,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 6,
					},
				},
				{
					AttributeId: stringAttributeID,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:        AttrAttributeType_STRING,
						Valid:       true,
						StringValue: "test",
					},
				},
				{
					AttributeId: boolAttributeID,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_BOOLEAN,
						Valid:     true,
						BoolValue: true,
					},
				},
				{
					AttributeId: listAttributeID,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_LIST,
						Valid:     true,
						ListValue: []int64{1},
					},
				},
				{
					AttributeId: treeAttributeID,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_LIST,
						Valid:     true,
						ListValue: []int64{25},
					},
				},
				{
					AttributeId: treeMultipleAttributeID,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_LIST,
						Valid:     true,
						ListValue: []int64{28, 29},
					},
				},
			},
		},
	)
	require.NoError(t, err)

	// check values
	values, err = client.GetValues(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&AttrValuesRequest{
			ItemId:   itemID,
			Language: "en",
		},
	)
	require.NoError(t, err)
	requireValues(t, values)

	// check user values by item_id
	userValues, err := client.GetUserValues(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&AttrUserValuesRequest{
			ItemId:   itemID,
			Language: "en",
			Fields: &AttrUserValuesFields{
				ValueText: true,
			},
		},
	)
	require.NoError(t, err)
	requireUserValues(t, userValues)
}

func TestGetEmptyValues(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, "frontend", "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := NewAttrsClient(conn)

	itemID := createItem(t, conn, cnt, &APIItem{
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
		Name:       "TestGetEmptyValues",
	})

	values, err := client.GetValues(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&AttrValuesRequest{
			ItemId:   itemID,
			Language: "en",
		},
	)
	require.NoError(t, err)
	require.Empty(t, values.GetItems())

	t.Helper()

	_, err = client.SetUserValues(
		metadata.AppendToOutgoingContext(
			t.Context(),
			authorizationHeader,
			bearerPrefix+token.AccessToken,
		),
		&AttrSetUserValuesRequest{
			Items: []*AttrUserValue{
				{
					AttributeId: floatEmptyAttributeID,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:    AttrAttributeType_FLOAT,
						Valid:   true,
						IsEmpty: true,
					},
				},
				{
					AttributeId: intEmptyAttributeID,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:    AttrAttributeType_INTEGER,
						Valid:   true,
						IsEmpty: true,
					},
				},
				{
					AttributeId: stringEmptyAttributeID,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:    AttrAttributeType_STRING,
						Valid:   true,
						IsEmpty: true,
					},
				},
				{
					AttributeId: boolEmptyAttributeID,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:    AttrAttributeType_BOOLEAN,
						Valid:   true,
						IsEmpty: true,
					},
				},
				{
					AttributeId: listEmptyAttributeID,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_LIST,
						Valid:     true,
						IsEmpty:   true,
						ListValue: []int64{},
					},
				},
				{
					AttributeId: treeEmptyAttributeID,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_LIST,
						Valid:     true,
						IsEmpty:   true,
						ListValue: []int64{},
					},
				},
			},
		},
	)
	require.NoError(t, err)

	// check values
	values, err = client.GetValues(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&AttrValuesRequest{
			ItemId:   itemID,
			Language: "en",
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, values.GetItems())

	var (
		floatEmptyFound  bool
		intEmptyFound    bool
		stringEmptyFound bool
		boolEmptyFound   bool
		listEmptyFound   bool
		treeEmptyFound   bool
	)

	for _, val := range values.GetItems() {
		switch val.GetAttributeId() {
		case floatEmptyAttributeID:
			floatEmptyFound = true

			require.True(t, val.GetValue().GetIsEmpty())
			require.True(t, val.GetValue().GetValid())
		case intEmptyAttributeID:
			intEmptyFound = true

			require.True(t, val.GetValue().GetIsEmpty())
			require.True(t, val.GetValue().GetValid())

		case stringEmptyAttributeID:
			stringEmptyFound = true

			require.True(t, val.GetValue().GetIsEmpty())
			require.True(t, val.GetValue().GetValid())
		case boolEmptyAttributeID:
			boolEmptyFound = true

			require.True(t, val.GetValue().GetIsEmpty())
			require.True(t, val.GetValue().GetValid())
		case listEmptyAttributeID:
			listEmptyFound = true

			require.True(t, val.GetValue().GetIsEmpty())
			require.True(t, val.GetValue().GetValid())
		case treeEmptyAttributeID:
			treeEmptyFound = true

			require.True(t, val.GetValue().GetIsEmpty())
			require.True(t, val.GetValue().GetValid())
		}
	}

	require.True(t, floatEmptyFound)
	require.True(t, intEmptyFound)
	require.True(t, stringEmptyFound)
	require.True(t, boolEmptyFound)
	require.True(t, listEmptyFound)
	require.True(t, treeEmptyFound)
}

func TestConflicts(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")

	kc := cnt.Keycloak()
	adminToken, err := kc.Login(
		ctx,
		"frontend",
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	client := NewAttrsClient(conn)

	_, err = client.GetConflicts(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&AttrConflictsRequest{
			Filter:   AttrConflictsRequest_ALL,
			Page:     0,
			Language: "en",
		},
	)
	require.NoError(t, err)

	_, err = client.GetConflicts(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&AttrConflictsRequest{
			Filter:   AttrConflictsRequest_MINUS_WEIGHT,
			Page:     0,
			Language: "en",
		},
	)
	require.NoError(t, err)

	_, err = client.GetConflicts(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&AttrConflictsRequest{
			Filter:   AttrConflictsRequest_I_DISAGREE,
			Page:     0,
			Language: "en",
		},
	)
	require.NoError(t, err)

	_, err = client.GetConflicts(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&AttrConflictsRequest{
			Filter:   AttrConflictsRequest_DO_NOT_AGREE_WITH_ME,
			Page:     0,
			Language: "en",
		},
	)
	require.NoError(t, err)
}

func TestValuesInherits(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, "frontend", "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := NewAttrsClient(conn)

	itemID := createItem(t, conn, cnt, &APIItem{
		Name:       "TestValuesInherits Parent",
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
		IsGroup:    true,
	})

	childItemID := createItem(t, conn, cnt, &APIItem{
		Name:       "TestValuesInherits Child",
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	itemsClient := NewItemsClient(conn)

	// admin
	adminToken, err := kc.Login(
		ctx,
		"frontend",
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	_, err = itemsClient.CreateItemParent(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&ItemParent{
			ItemId: childItemID, ParentId: itemID, Catname: "vehicle1",
		},
	)
	require.NoError(t, err)

	_, err = client.SetUserValues(
		metadata.AppendToOutgoingContext(
			t.Context(),
			authorizationHeader,
			bearerPrefix+token.AccessToken,
		),
		&AttrSetUserValuesRequest{
			Items: []*AttrUserValue{
				{
					AttributeId: intAttributeID,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 77,
					},
				},
			},
		},
	)
	require.NoError(t, err)

	// check values
	for _, currentItemID := range []int64{itemID, childItemID} {
		values, err := client.GetValues(
			metadata.AppendToOutgoingContext(
				ctx,
				authorizationHeader,
				bearerPrefix+token.AccessToken,
			),
			&AttrValuesRequest{
				ItemId:   currentItemID,
				Language: "en",
			},
		)
		require.NoError(t, err)
		require.NotEmpty(t, values.GetItems())

		var intFound bool

		for _, val := range values.GetItems() {
			require.Equal(t, val.GetItemId(), currentItemID)

			if val.GetAttributeId() == intAttributeID {
				intFound = true

				require.True(t, val.GetValue().GetValid())
				require.False(t, val.GetValue().GetIsEmpty())
				require.Equal(t, int32(77), val.GetValue().GetIntValue())
			}
		}

		require.True(t, intFound)
	}
}

func TestEngineValuesApplied(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, "frontend", "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := NewAttrsClient(conn)

	engineItemID := createItem(t, conn, cnt, &APIItem{
		Name:       "TestEngineValuesApplied Engine",
		ItemTypeId: ItemType_ITEM_TYPE_ENGINE,
	})

	itemID := createItem(t, conn, cnt, &APIItem{
		Name:         "TestEngineValuesApplied Engine",
		ItemTypeId:   ItemType_ITEM_TYPE_VEHICLE,
		EngineItemId: engineItemID,
	})

	_, err = client.SetUserValues(
		metadata.AppendToOutgoingContext(
			t.Context(),
			authorizationHeader,
			bearerPrefix+token.AccessToken,
		),
		&AttrSetUserValuesRequest{
			Items: []*AttrUserValue{
				{
					AttributeId: schema.EngineTypeAttr,
					ItemId:      engineItemID,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_TREE,
						Valid:     true,
						ListValue: []int64{104, 105},
					},
				},
			},
		},
	)
	require.NoError(t, err)

	// check values
	for _, currentItemID := range []int64{itemID, engineItemID} {
		values, err := client.GetValues(
			metadata.AppendToOutgoingContext(
				ctx,
				authorizationHeader,
				bearerPrefix+token.AccessToken,
			),
			&AttrValuesRequest{
				ItemId:   currentItemID,
				Language: "en",
			},
		)
		require.NoError(t, err)
		require.NotEmpty(t, values.GetItems())

		var attributeFound bool

		for _, val := range values.GetItems() {
			require.Equal(t, val.GetItemId(), currentItemID)

			if val.GetAttributeId() == schema.EngineTypeAttr {
				attributeFound = true

				require.True(t, val.GetValue().GetValid())
				require.False(t, val.GetValue().GetIsEmpty())
				require.Equal(t, []int64{104}, val.GetValue().GetListValue())
			}
		}

		require.True(t, attributeFound)
	}
}

func TestSetUserValuesList(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, "frontend", "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := NewAttrsClient(conn)

	itemID := createItem(t, conn, cnt, &APIItem{
		Name:       "TestSetUserValuesList",
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	cases := []struct {
		Input   []int64
		IsEmpty bool
		Output  []int64
	}{
		{
			Input:   []int64{999},
			IsEmpty: false,
			Output:  []int64{},
		},
		{
			Input:   []int64{1, 104, 105},
			IsEmpty: false,
			Output:  []int64{104},
		},
		{
			Input:   []int64{105, 104},
			IsEmpty: false,
			Output:  []int64{104},
		},
		{
			Input:   []int64{},
			IsEmpty: false,
			Output:  []int64{},
		},
		{
			Input:   []int64{},
			IsEmpty: true,
			Output:  nil,
		},
	}

	for _, testCase := range cases {
		_, err = client.SetUserValues(
			metadata.AppendToOutgoingContext(
				t.Context(),
				authorizationHeader,
				bearerPrefix+token.AccessToken,
			),
			&AttrSetUserValuesRequest{
				Items: []*AttrUserValue{
					{
						AttributeId: schema.EngineTypeAttr,
						ItemId:      itemID,
						Value: &AttrValueValue{
							Type:      AttrAttributeType_TREE,
							Valid:     true,
							IsEmpty:   testCase.IsEmpty,
							ListValue: testCase.Input,
						},
					},
				},
			},
		)
		require.NoError(t, err)

		// check values
		values, err := client.GetValues(
			metadata.AppendToOutgoingContext(
				ctx,
				authorizationHeader,
				bearerPrefix+token.AccessToken,
			),
			&AttrValuesRequest{
				ItemId:   itemID,
				Language: "en",
			},
		)
		require.NoError(t, err)

		if len(testCase.Output) == 0 && !testCase.IsEmpty {
			require.Empty(t, values.GetItems())
		} else {
			require.NotEmpty(t, values.GetItems())

			var attributeFound bool

			for _, val := range values.GetItems() {
				require.Equal(t, val.GetItemId(), itemID)

				if val.GetAttributeId() == schema.EngineTypeAttr {
					attributeFound = true

					require.True(t, val.GetValue().GetValid())
					require.Equal(t, testCase.IsEmpty, val.GetValue().GetIsEmpty())
					require.Equal(t, testCase.Output, val.GetValue().GetListValue())
				}
			}

			require.True(t, attributeFound)
		}
	}
}

func TestSetValuesRaceConditions(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, "frontend", "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := NewAttrsClient(conn)

	itemID := createItem(t, conn, cnt, &APIItem{
		Name:       "TestSetValuesRaceConditions",
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	cases := []struct {
		AttributeID int64
		Value       *AttrValueValue
	}{
		{
			AttributeID: schema.EngineTypeAttr,
			Value: &AttrValueValue{
				Type:      AttrAttributeType_TREE,
				Valid:     true,
				IsEmpty:   false,
				ListValue: []int64{999},
			},
		},
		{
			AttributeID: schema.EngineTypeAttr,
			Value: &AttrValueValue{
				Type:      AttrAttributeType_TREE,
				Valid:     true,
				IsEmpty:   false,
				ListValue: []int64{1, 104, 105},
			},
		},
		{
			AttributeID: schema.EngineTypeAttr,
			Value: &AttrValueValue{
				Type:      AttrAttributeType_TREE,
				Valid:     true,
				IsEmpty:   false,
				ListValue: []int64{105, 104},
			},
		},
		{
			AttributeID: schema.EngineTypeAttr,
			Value: &AttrValueValue{
				Type:      AttrAttributeType_TREE,
				Valid:     true,
				IsEmpty:   false,
				ListValue: []int64{},
			},
		},
		{
			AttributeID: schema.EngineTypeAttr,
			Value: &AttrValueValue{
				Type:      AttrAttributeType_TREE,
				Valid:     true,
				IsEmpty:   true,
				ListValue: []int64{},
			},
		},
		{
			AttributeID: floatEmptyAttributeID,
			Value: &AttrValueValue{
				Type:    AttrAttributeType_FLOAT,
				Valid:   true,
				IsEmpty: true,
			},
		},
		{
			AttributeID: intEmptyAttributeID,
			Value: &AttrValueValue{
				Type:    AttrAttributeType_INTEGER,
				Valid:   true,
				IsEmpty: true,
			},
		},
		{
			AttributeID: stringEmptyAttributeID,
			Value: &AttrValueValue{
				Type:    AttrAttributeType_STRING,
				Valid:   true,
				IsEmpty: true,
			},
		},
		{
			AttributeID: boolEmptyAttributeID,
			Value: &AttrValueValue{
				Type:    AttrAttributeType_BOOLEAN,
				Valid:   true,
				IsEmpty: true,
			},
		},
		{
			AttributeID: listEmptyAttributeID,
			Value: &AttrValueValue{
				Type:      AttrAttributeType_LIST,
				Valid:     true,
				IsEmpty:   true,
				ListValue: []int64{},
			},
		},
		{
			AttributeID: treeEmptyAttributeID,
			Value: &AttrValueValue{
				Type:      AttrAttributeType_LIST,
				Valid:     true,
				IsEmpty:   true,
				ListValue: []int64{},
			},
		},
		{
			AttributeID: floatAttributeID,
			Value: &AttrValueValue{
				Type:       AttrAttributeType_FLOAT,
				Valid:      true,
				FloatValue: 7.091,
			},
		},
		{
			AttributeID: intAttributeID,
			Value: &AttrValueValue{
				Type:     AttrAttributeType_INTEGER,
				Valid:    true,
				IntValue: 6,
			},
		},
		{
			AttributeID: stringAttributeID,
			Value: &AttrValueValue{
				Type:        AttrAttributeType_STRING,
				Valid:       true,
				StringValue: "test",
			},
		},
		{
			AttributeID: boolAttributeID,
			Value: &AttrValueValue{
				Type:      AttrAttributeType_BOOLEAN,
				Valid:     true,
				BoolValue: true,
			},
		},
		{
			AttributeID: listAttributeID,
			Value: &AttrValueValue{
				Type:      AttrAttributeType_LIST,
				Valid:     true,
				ListValue: []int64{1},
			},
		},
		{
			AttributeID: treeAttributeID,
			Value: &AttrValueValue{
				Type:      AttrAttributeType_LIST,
				Valid:     true,
				ListValue: []int64{25},
			},
		},
		{
			AttributeID: treeMultipleAttributeID,
			Value: &AttrValueValue{
				Type:      AttrAttributeType_LIST,
				Valid:     true,
				ListValue: []int64{28, 29},
			},
		},
	}

	wg := sync.WaitGroup{}

	for range 3 {
		for _, testCase := range cases {
			wg.Add(1)

			go func(ctx context.Context) {
				defer wg.Done()

				_, err = client.SetUserValues(
					metadata.AppendToOutgoingContext(
						ctx,
						authorizationHeader,
						bearerPrefix+token.AccessToken,
					),
					&AttrSetUserValuesRequest{
						Items: []*AttrUserValue{
							{
								AttributeId: schema.EngineTypeAttr,
								ItemId:      itemID,
								Value:       testCase.Value,
							},
						},
					},
				)
				assert.NoError(t, err)

				// check values
				_, err := client.GetValues(
					metadata.AppendToOutgoingContext(
						ctx,
						authorizationHeader,
						bearerPrefix+token.AccessToken,
					),
					&AttrValuesRequest{
						ItemId:   itemID,
						Language: "en",
					},
				)
				assert.NoError(t, err)
			}(ctx)
		}
	}

	wg.Wait()
}

func TestValuesInheritsThroughItem(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, "frontend", "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	usersClient := NewUsersClient(conn)
	me, err := usersClient.Me(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&APIMeRequest{},
	)
	require.NoError(t, err)
	require.NotNil(t, me)

	client := NewAttrsClient(conn)

	itemID := createItem(t, conn, cnt, &APIItem{
		Name:       "TestValuesInheritsThroughItem item",
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
		IsGroup:    true,
	})

	childItemID := createItem(t, conn, cnt, &APIItem{
		Name:       "TestValuesInheritsThroughItem child",
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
		IsGroup:    true,
	})

	inheritorItemID := createItem(t, conn, cnt, &APIItem{
		Name:       "TestValuesInheritsThroughItem interior",
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	itemsClient := NewItemsClient(conn)

	// admin
	adminToken, err := kc.Login(
		ctx,
		"frontend",
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	_, err = itemsClient.CreateItemParent(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&ItemParent{
			ItemId: childItemID, ParentId: itemID, Catname: "vehicle1",
		},
	)
	require.NoError(t, err)

	_, err = itemsClient.CreateItemParent(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&ItemParent{
			ItemId: inheritorItemID, ParentId: childItemID, Catname: "vehicle1",
		},
	)
	require.NoError(t, err)

	_, err = client.SetUserValues(
		metadata.AppendToOutgoingContext(
			t.Context(),
			authorizationHeader,
			bearerPrefix+token.AccessToken,
		),
		&AttrSetUserValuesRequest{
			Items: []*AttrUserValue{
				{
					AttributeId: intAttributeID,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 77,
					},
				},
			},
		},
	)
	require.NoError(t, err)

	// check values
	for _, currentItemID := range []int64{itemID, childItemID, inheritorItemID} {
		values, err := client.GetValues(
			metadata.AppendToOutgoingContext(
				ctx,
				authorizationHeader,
				bearerPrefix+token.AccessToken,
			),
			&AttrValuesRequest{
				ItemId:   currentItemID,
				Language: "en",
			},
		)
		require.NoError(t, err)
		require.NotEmpty(t, values.GetItems())

		var intFound bool

		for _, val := range values.GetItems() {
			require.Equal(t, val.GetItemId(), currentItemID)

			if val.GetAttributeId() == intAttributeID {
				intFound = true

				require.True(t, val.GetValue().GetValid())
				require.False(t, val.GetValue().GetIsEmpty())
				require.Equal(t, int32(77), val.GetValue().GetIntValue())
			}
		}

		require.True(t, intFound)
	}

	// delete user value
	_, err = client.DeleteUserValues(
		metadata.AppendToOutgoingContext(
			t.Context(),
			authorizationHeader,
			bearerPrefix+token.AccessToken,
		),
		&DeleteAttrUserValuesRequest{
			AttributeId: intAttributeID,
			ItemId:      itemID,
			UserId:      me.GetId(),
		},
	)
	require.NoError(t, err)

	// check values
	for _, currentItemID := range []int64{itemID, childItemID, inheritorItemID} {
		values, err := client.GetValues(
			metadata.AppendToOutgoingContext(
				ctx,
				authorizationHeader,
				bearerPrefix+token.AccessToken,
			),
			&AttrValuesRequest{
				ItemId:   currentItemID,
				Language: "en",
			},
		)
		require.NoError(t, err)
		require.Empty(t, values.GetItems())
	}
}

func TestInheritedValueOverridden(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, "frontend", "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := NewAttrsClient(conn)

	itemID := createItem(t, conn, cnt, &APIItem{
		Name:       "TestInheritedValueOverridden",
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
		IsGroup:    true,
	})

	childItemID := createItem(t, conn, cnt, &APIItem{
		Name:       "TestInheritedValueOverridden child",
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	itemsClient := NewItemsClient(conn)

	_, err = itemsClient.CreateItemParent(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&ItemParent{
			ItemId: childItemID, ParentId: itemID, Catname: "vehicle1",
		},
	)
	require.NoError(t, err)

	_, err = client.SetUserValues(
		metadata.AppendToOutgoingContext(
			t.Context(),
			authorizationHeader,
			bearerPrefix+token.AccessToken,
		),
		&AttrSetUserValuesRequest{
			Items: []*AttrUserValue{
				{
					AttributeId: intAttributeID,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 77,
					},
				},
			},
		},
	)
	require.NoError(t, err)

	_, err = client.SetUserValues(
		metadata.AppendToOutgoingContext(
			t.Context(),
			authorizationHeader,
			bearerPrefix+token.AccessToken,
		),
		&AttrSetUserValuesRequest{
			Items: []*AttrUserValue{
				{
					AttributeId: intAttributeID,
					ItemId:      childItemID,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 219,
					},
				},
			},
		},
	)
	require.NoError(t, err)

	// check values
	values, err := client.GetValues(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&AttrValuesRequest{
			ItemId:   itemID,
			Language: "en",
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, values.GetItems())

	intFound := false

	for _, val := range values.GetItems() {
		require.Equal(t, val.GetItemId(), itemID)

		if val.GetAttributeId() == intAttributeID {
			intFound = true

			require.True(t, val.GetValue().GetValid())
			require.False(t, val.GetValue().GetIsEmpty())
			require.Equal(t, int32(77), val.GetValue().GetIntValue())
		}
	}

	require.True(t, intFound)

	// check values
	values, err = client.GetValues(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&AttrValuesRequest{
			ItemId:   childItemID,
			Language: "en",
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, values.GetItems())

	intFound = false

	for _, val := range values.GetItems() {
		require.Equal(t, val.GetItemId(), childItemID)

		if val.GetAttributeId() == intAttributeID {
			intFound = true

			require.True(t, val.GetValue().GetValid())
			require.False(t, val.GetValue().GetIsEmpty())
			require.Equal(t, int32(219), val.GetValue().GetIntValue())
		}
	}

	require.True(t, intFound)
}

func TestMoveValues(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, "frontend", "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := NewAttrsClient(conn)

	srcItemID := createItem(t, conn, cnt, &APIItem{
		Name:       "TestMoveValues",
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	destItemID := createItem(t, conn, cnt, &APIItem{
		Name:       "TestMoveValues dest",
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	_, err = client.SetUserValues(
		metadata.AppendToOutgoingContext(
			t.Context(),
			authorizationHeader,
			bearerPrefix+token.AccessToken,
		),
		&AttrSetUserValuesRequest{
			Items: []*AttrUserValue{
				{
					AttributeId: intAttributeID,
					ItemId:      srcItemID,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 77,
					},
				},
			},
		},
	)
	require.NoError(t, err)

	_, err = client.MoveUserValues(
		metadata.AppendToOutgoingContext(
			t.Context(),
			authorizationHeader,
			bearerPrefix+token.AccessToken,
		),
		&MoveAttrUserValuesRequest{
			SrcItemId:  srcItemID,
			DestItemId: destItemID,
		},
	)
	require.NoError(t, err)

	// check values
	values, err := client.GetValues(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&AttrValuesRequest{
			ItemId:   destItemID,
			Language: "en",
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, values.GetItems())

	intFound := false

	for _, val := range values.GetItems() {
		require.Equal(t, val.GetItemId(), destItemID)

		if val.GetAttributeId() == intAttributeID {
			intFound = true

			require.True(t, val.GetValue().GetValid())
			require.False(t, val.GetValue().GetIsEmpty())
			require.Equal(t, int32(77), val.GetValue().GetIntValue())
		}
	}

	require.True(t, intFound)

	// check values
	values, err = client.GetValues(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&AttrValuesRequest{
			ItemId:   srcItemID,
			Language: "en",
		},
	)
	require.NoError(t, err)
	require.Empty(t, values.GetItems())
}

func TestValueDateMustChangesWhenValueChanged(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, "frontend", "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := NewAttrsClient(conn)

	itemID := createItem(t, conn, cnt, &APIItem{
		Name:       "TestValueDateMustChangesWhenValueChanged",
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	// set initial value
	_, err = client.SetUserValues(
		metadata.AppendToOutgoingContext(
			t.Context(),
			authorizationHeader,
			bearerPrefix+token.AccessToken,
		),
		&AttrSetUserValuesRequest{
			Items: []*AttrUserValue{
				{
					AttributeId: intAttributeID,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 77,
					},
				},
			},
		},
	)
	require.NoError(t, err)

	// check values
	values, err := client.GetUserValues(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&AttrUserValuesRequest{
			ItemId:   itemID,
			Language: "en",
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, values.GetItems())
	require.Len(t, values.GetItems(), 1)
	value := values.GetItems()[0]

	require.Equal(t, itemID, value.GetItemId())
	require.Equal(t, intAttributeID, value.GetAttributeId())
	require.True(t, value.GetValue().GetValid())
	require.False(t, value.GetValue().GetIsEmpty())
	require.Equal(t, int32(77), value.GetValue().GetIntValue())

	// set secondary value
	time.Sleep(time.Second)

	_, err = client.SetUserValues(
		metadata.AppendToOutgoingContext(
			t.Context(),
			authorizationHeader,
			bearerPrefix+token.AccessToken,
		),
		&AttrSetUserValuesRequest{
			Items: []*AttrUserValue{
				{
					AttributeId: intAttributeID,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 78,
					},
				},
			},
		},
	)
	require.NoError(t, err)

	// check values
	values, err = client.GetUserValues(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&AttrUserValuesRequest{
			ItemId:   itemID,
			Language: "en",
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, values.GetItems())
	require.Len(t, values.GetItems(), 1)
	secondaryValue := values.GetItems()[0]

	require.Equal(t, itemID, secondaryValue.GetItemId())
	require.Equal(t, intAttributeID, secondaryValue.GetAttributeId())
	require.True(t, secondaryValue.GetValue().GetValid())
	require.False(t, secondaryValue.GetValue().GetIsEmpty())
	require.Equal(t, int32(78), secondaryValue.GetValue().GetIntValue())

	require.NotEqual(t, value.GetUpdateDate().AsTime(), secondaryValue.GetUpdateDate().AsTime())

	// set secondary value again
	time.Sleep(time.Second)

	_, err = client.SetUserValues(
		metadata.AppendToOutgoingContext(
			t.Context(),
			authorizationHeader,
			bearerPrefix+token.AccessToken,
		),
		&AttrSetUserValuesRequest{
			Items: []*AttrUserValue{
				{
					AttributeId: intAttributeID,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 78,
					},
				},
			},
		},
	)
	require.NoError(t, err)

	// check values
	values, err = client.GetUserValues(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&AttrUserValuesRequest{
			ItemId:   itemID,
			Language: "en",
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, values.GetItems())
	require.Len(t, values.GetItems(), 1)
	thirdValue := values.GetItems()[0]

	require.Equal(t, itemID, thirdValue.GetItemId())
	require.Equal(t, intAttributeID, thirdValue.GetAttributeId())
	require.True(t, thirdValue.GetValue().GetValid())
	require.False(t, thirdValue.GetValue().GetIsEmpty())
	require.Equal(t, int32(78), thirdValue.GetValue().GetIntValue())

	require.Equal(t, secondaryValue.GetUpdateDate().AsTime(), thirdValue.GetUpdateDate().AsTime())
}

func TestNonMultipleValuesFiltered(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, "frontend", "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := NewAttrsClient(conn)

	itemID := createItem(t, conn, cnt, &APIItem{
		Name:       "TestNonMultipleValuesFiltered",
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	// set value
	_, err = client.SetUserValues(
		metadata.AppendToOutgoingContext(
			t.Context(),
			authorizationHeader,
			bearerPrefix+token.AccessToken,
		),
		&AttrSetUserValuesRequest{
			Items: []*AttrUserValue{
				{
					AttributeId: 20,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_LIST,
						Valid:     true,
						ListValue: []int64{1, 2},
					},
				},
			},
		},
	)
	require.NoError(t, err)

	// check values
	values, err := client.GetUserValues(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&AttrUserValuesRequest{
			ItemId:   itemID,
			Language: "en",
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, values.GetItems())
	require.Len(t, values.GetItems(), 1)
	value := values.GetItems()[0]

	require.Equal(t, itemID, value.GetItemId())
	require.Equal(t, int64(20), value.GetAttributeId())
	require.True(t, value.GetValue().GetValid())
	require.False(t, value.GetValue().GetIsEmpty())
	require.Len(t, value.GetValue().GetListValue(), 1)
}

func TestEmptyListValueConsiderAsNonValid(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, "frontend", "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := NewAttrsClient(conn)

	itemID := createItem(t, conn, cnt, &APIItem{
		Name:       "TestEmptyListValueConsiderAsNonValid",
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	// set value
	_, err = client.SetUserValues(
		metadata.AppendToOutgoingContext(
			t.Context(),
			authorizationHeader,
			bearerPrefix+token.AccessToken,
		),
		&AttrSetUserValuesRequest{
			Items: []*AttrUserValue{
				{
					AttributeId: 20,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_LIST,
						Valid:     true,
						ListValue: []int64{},
					},
				},
			},
		},
	)
	require.NoError(t, err)

	// check values
	userValues, err := client.GetUserValues(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&AttrUserValuesRequest{
			ItemId:   itemID,
			Language: "en",
		},
	)
	require.NoError(t, err)
	require.Empty(t, userValues.GetItems())
}

func TestEmptyStringValueConsiderAsNonValid(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, "frontend", "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := NewAttrsClient(conn)

	itemID := createItem(t, conn, cnt, &APIItem{
		Name:       "TestEmptyStringValueConsiderAsNonValid",
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	// set value
	_, err = client.SetUserValues(
		metadata.AppendToOutgoingContext(
			t.Context(),
			authorizationHeader,
			bearerPrefix+token.AccessToken,
		),
		&AttrSetUserValuesRequest{
			Items: []*AttrUserValue{
				{
					AttributeId: 8,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:        AttrAttributeType_STRING,
						Valid:       true,
						StringValue: "",
					},
				},
			},
		},
	)
	require.NoError(t, err)

	// check values
	userValues, err := client.GetUserValues(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&AttrUserValuesRequest{
			ItemId:   itemID,
			Language: "en",
		},
	)
	require.NoError(t, err)
	require.Empty(t, userValues.GetItems())
}

func TestSpecifications(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, "frontend", "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := NewAttrsClient(conn)
	itemsClient := NewItemsClient(conn)

	itemID := createItem(t, conn, cnt, &APIItem{
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
		Name:       "Test",
		Body:       "E31",
	})

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	engineName := fmt.Sprintf("Peugeot-%d-Engine", randomInt)
	engineID := createItem(t, conn, cnt, &APIItem{
		Name:       engineName,
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_ENGINE,
	})

	_, err = itemsClient.UpdateItem(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdateItemRequest{
			Item: &APIItem{
				Id:            itemID,
				EngineItemId:  engineID,
				EngineInherit: false,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"engine_item_id", "engine_inherit"}},
		},
	)
	require.NoError(t, err)

	_, err = client.SetUserValues(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&AttrSetUserValuesRequest{
			Items: []*AttrUserValue{
				{
					AttributeId: schema.EngineTypeAttr,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_TREE,
						Valid:     true,
						IsEmpty:   false,
						ListValue: []int64{105},
					},
				},
				{
					AttributeId: schema.EngineCylinderDiameter,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:    AttrAttributeType_FLOAT,
						Valid:   true,
						IsEmpty: true,
					},
				},
				{
					AttributeId: schema.WidthAttr,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:    AttrAttributeType_INTEGER,
						Valid:   true,
						IsEmpty: true,
					},
				},
				{
					AttributeId: schema.RearSuspensionType,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:    AttrAttributeType_STRING,
						Valid:   true,
						IsEmpty: true,
					},
				},
				{
					AttributeId: schema.ABSAttr,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:    AttrAttributeType_BOOLEAN,
						Valid:   true,
						IsEmpty: true,
					},
				},
				{
					AttributeId: schema.EnginePlacementOrientationAttr,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_LIST,
						Valid:     true,
						IsEmpty:   true,
						ListValue: []int64{},
					},
				},
				{
					AttributeId: schema.DriveUnitAttr,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_LIST,
						Valid:     true,
						IsEmpty:   true,
						ListValue: []int64{},
					},
				},
				{
					AttributeId: schema.TurningDiameterAttr,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 7.091,
					},
				},
				{
					AttributeId: schema.LengthAttr,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 6,
					},
				},
				{
					AttributeId: schema.FrontSuspensionTypeAttr,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:        AttrAttributeType_STRING,
						Valid:       true,
						StringValue: "suspension test",
					},
				},
				{
					AttributeId: schema.SpeedLimiterAttr,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_BOOLEAN,
						Valid:     true,
						BoolValue: true,
					},
				},
				{
					AttributeId: schema.EnginePlacementPlacementAttr,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_LIST,
						Valid:     true,
						ListValue: []int64{1},
					},
				},
				{
					AttributeId: schema.FuelSupplySystemAttr,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_LIST,
						Valid:     true,
						ListValue: []int64{25},
					},
				},
				{
					AttributeId: schema.FuelTypeAttr,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_LIST,
						Valid:     true,
						ListValue: []int64{28, 29},
					},
				},
				{
					AttributeId: schema.EngineConfigurationCylindersCountAttr,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Valid:    true,
						IntValue: 6,
					},
				},
				{
					AttributeId: schema.EngineConfigurationCylindersLayoutAttr,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Valid:     true,
						ListValue: []int64{8},
					},
				},
				{
					AttributeId: schema.EngineConfigurationValvesCountAttr,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Valid:    true,
						IntValue: 4,
					},
				},
			},
		},
	)
	require.NoError(t, err)

	res, err := client.GetSpecifications(
		metadata.AppendToOutgoingContext(
			t.Context(),
			authorizationHeader,
			bearerPrefix+token.AccessToken,
		),
		&GetSpecificationsRequest{
			ItemId:   itemID,
			Language: "en",
		},
	)
	require.NoError(t, err)
	require.Contains(t, res.GetHtml(), "Piston")
	require.NotContains(t, res.GetHtml(), "cylinder diameter")
	require.Contains(t, res.GetHtml(), "diameter")
	require.Contains(t, res.GetHtml(), `7.1 <span class="unit" title="meter">m</span>`)
	require.Contains(t, res.GetHtml(), "geometry")
	require.Contains(t, res.GetHtml(), "dimensions")
	require.Contains(t, res.GetHtml(), "length")
	require.Contains(t, res.GetHtml(), `6 <span class="unit" title="millimeter">mm</span>`)
	require.Contains(t, res.GetHtml(), "suspension and steering")
	require.Contains(t, res.GetHtml(), "front suspension type (deprecated)")
	require.Contains(t, res.GetHtml(), "suspension test")
	require.Contains(t, res.GetHtml(), "dynamic properties")
	require.Contains(t, res.GetHtml(), "speed limiter")
	require.Contains(t, res.GetHtml(), "yes")
	require.Contains(t, res.GetHtml(), "location")
	require.Contains(t, res.GetHtml(), "at front")
	require.Contains(t, res.GetHtml(), "supply system")
	require.Contains(t, res.GetHtml(), "2 carburetors")
	require.Contains(t, res.GetHtml(), "fuel")
	require.Contains(t, res.GetHtml(), "petrol")
	require.Contains(t, res.GetHtml(), "hydrogen")
	require.Contains(t, res.GetHtml(), "V6/4")
	require.Contains(t, res.GetHtml(), engineName)
}

func TestChildSpecifications(t *testing.T) { //nolint: maintidx
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, "frontend", "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := NewAttrsClient(conn)
	itemsClient := NewItemsClient(conn)

	itemID := createItem(t, conn, cnt, &APIItem{
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
		Name:       "Test",
		Body:       "E31",
		IsGroup:    true,
	})

	child1ID := createItem(t, conn, cnt, &APIItem{
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
		Name:       "Test Child 1",
		Body:       "E31",
	})

	_, err = itemsClient.CreateItemParent(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&ItemParent{
			ItemId:   child1ID,
			ParentId: itemID,
		},
	)
	require.NoError(t, err)

	child2ID := createItem(t, conn, cnt, &APIItem{
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
		Name:       "Test Child 1",
		Body:       "E31",
	})

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	_, err = itemsClient.CreateItemParent(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&ItemParent{
			ItemId:   child2ID,
			ParentId: itemID,
		},
	)
	require.NoError(t, err)

	engineName := fmt.Sprintf("Peugeot-%d-Engine", randomInt)
	engineID := createItem(t, conn, cnt, &APIItem{
		Name:       engineName,
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_ENGINE,
	})

	_, err = itemsClient.UpdateItem(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdateItemRequest{
			Item: &APIItem{
				Id:            child1ID,
				EngineItemId:  engineID,
				EngineInherit: false,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"engine_item_id", "engine_inherit"}},
		},
	)
	require.NoError(t, err)

	_, err = client.SetUserValues(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&AttrSetUserValuesRequest{
			Items: []*AttrUserValue{
				{
					AttributeId: schema.FuelSupplySystemAttr,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_LIST,
						Valid:     true,
						ListValue: []int64{25},
					},
				},
				{
					AttributeId: schema.EngineCylinderDiameter,
					ItemId:      child1ID,
					Value: &AttrValueValue{
						Type:    AttrAttributeType_FLOAT,
						Valid:   true,
						IsEmpty: true,
					},
				},
				{
					AttributeId: schema.WidthAttr,
					ItemId:      child2ID,
					Value: &AttrValueValue{
						Type:    AttrAttributeType_INTEGER,
						Valid:   true,
						IsEmpty: true,
					},
				},
				{
					AttributeId: schema.ABSAttr,
					ItemId:      child1ID,
					Value: &AttrValueValue{
						Type:    AttrAttributeType_BOOLEAN,
						Valid:   true,
						IsEmpty: true,
					},
				},
				{
					AttributeId: schema.EngineTypeAttr,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_TREE,
						Valid:     true,
						IsEmpty:   false,
						ListValue: []int64{105},
					},
				},
				{
					AttributeId: schema.EnginePlacementOrientationAttr,
					ItemId:      child2ID,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_LIST,
						Valid:     true,
						IsEmpty:   true,
						ListValue: []int64{},
					},
				},
				{
					AttributeId: schema.DriveUnitAttr,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_LIST,
						Valid:     true,
						IsEmpty:   true,
						ListValue: []int64{},
					},
				},
				{
					AttributeId: schema.TurningDiameterAttr,
					ItemId:      child1ID,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 7.091,
					},
				},
				{
					AttributeId: schema.LengthAttr,
					ItemId:      child2ID,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 6,
					},
				},
				{
					AttributeId: schema.FrontSuspensionTypeAttr,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:        AttrAttributeType_STRING,
						Valid:       true,
						StringValue: "suspension test",
					},
				},
				{
					AttributeId: schema.RearSuspensionType,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:    AttrAttributeType_STRING,
						Valid:   true,
						IsEmpty: true,
					},
				},
				{
					AttributeId: schema.SpeedLimiterAttr,
					ItemId:      child1ID,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_BOOLEAN,
						Valid:     true,
						BoolValue: true,
					},
				},
				{
					AttributeId: schema.EnginePlacementPlacementAttr,
					ItemId:      child2ID,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_LIST,
						Valid:     true,
						ListValue: []int64{1},
					},
				},

				{
					AttributeId: schema.FuelTypeAttr,
					ItemId:      child1ID,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_LIST,
						Valid:     true,
						ListValue: []int64{28, 29},
					},
				},
				{
					AttributeId: schema.EngineConfigurationCylindersCountAttr,
					ItemId:      child2ID,
					Value: &AttrValueValue{
						Valid:    true,
						IntValue: 6,
					},
				},
				{
					AttributeId: schema.EngineConfigurationCylindersLayoutAttr,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Valid:     true,
						ListValue: []int64{8},
					},
				},
				{
					AttributeId: schema.EngineConfigurationValvesCountAttr,
					ItemId:      child1ID,
					Value: &AttrValueValue{
						Valid:    true,
						IntValue: 4,
					},
				},
			},
		},
	)
	require.NoError(t, err)

	res, err := client.GetChildSpecifications(
		metadata.AppendToOutgoingContext(
			t.Context(),
			authorizationHeader,
			bearerPrefix+token.AccessToken,
		),
		&GetSpecificationsRequest{
			ItemId:   itemID,
			Language: "en",
		},
	)
	require.NoError(t, err)
	require.Contains(t, res.GetHtml(), "Piston")
	require.NotContains(t, res.GetHtml(), "cylinder diameter")
	require.Contains(t, res.GetHtml(), "diameter")
	require.Contains(t, res.GetHtml(), `7.1 <span class="unit" title="meter">m</span>`)
	require.Contains(t, res.GetHtml(), "geometry")
	require.Contains(t, res.GetHtml(), "dimensions")
	require.Contains(t, res.GetHtml(), "length")
	require.Contains(t, res.GetHtml(), `6 <span class="unit" title="millimeter">mm</span>`)
	require.Contains(t, res.GetHtml(), "suspension and steering")
	require.Contains(t, res.GetHtml(), "front suspension type (deprecated)")
	require.Contains(t, res.GetHtml(), "suspension test")
	require.Contains(t, res.GetHtml(), "dynamic properties")
	require.Contains(t, res.GetHtml(), "speed limiter")
	require.Contains(t, res.GetHtml(), "yes")
	require.Contains(t, res.GetHtml(), "location")
	require.Contains(t, res.GetHtml(), "at front")
	require.Contains(t, res.GetHtml(), "supply system")
	require.Contains(t, res.GetHtml(), "2 carburetors")
	require.Contains(t, res.GetHtml(), "fuel")
	require.Contains(t, res.GetHtml(), "petrol")
	require.Contains(t, res.GetHtml(), "hydrogen")
	require.Contains(t, res.GetHtml(), "V?/4")
	require.Contains(t, res.GetHtml(), "V6")
	require.Contains(t, res.GetHtml(), engineName)
}

func TestLocalizedFormat(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, "frontend", "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := NewAttrsClient(conn)

	itemID := createItem(t, conn, cnt, &APIItem{
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
		Name:       "Test",
		Body:       "E31",
		IsGroup:    true,
	})

	_, err = client.SetUserValues(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&AttrSetUserValuesRequest{
			Items: []*AttrUserValue{
				{
					AttributeId: schema.MaxSpeedAttr,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 250,
					},
				},
			},
		},
	)
	require.NoError(t, err)

	res, err := client.GetSpecifications(
		metadata.AppendToOutgoingContext(
			t.Context(),
			authorizationHeader,
			bearerPrefix+token.AccessToken,
		),
		&GetSpecificationsRequest{
			ItemId:   itemID,
			Language: "en",
		},
	)
	require.NoError(t, err)
	require.Contains(t, res.GetHtml(), "250.0")

	res, err = client.GetSpecifications(
		metadata.AppendToOutgoingContext(
			t.Context(),
			authorizationHeader,
			bearerPrefix+token.AccessToken,
		),
		&GetSpecificationsRequest{
			ItemId:   itemID,
			Language: "ru",
		},
	)
	require.NoError(t, err)
	require.Contains(t, res.GetHtml(), "250,0")
}

func TestSetUserValuesIsEmpty(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, "frontend", "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := NewAttrsClient(conn)

	itemID := createItem(t, conn, cnt, &APIItem{
		Name:       "TestSetUserValuesIsEmpty",
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	_, err = client.SetUserValues(
		metadata.AppendToOutgoingContext(
			t.Context(),
			authorizationHeader,
			bearerPrefix+token.AccessToken,
		),
		&AttrSetUserValuesRequest{
			Items: []*AttrUserValue{
				{
					AttributeId: schema.EngineTypeAttr,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Valid:   true,
						IsEmpty: true,
					},
				},
			},
		},
	)
	require.NoError(t, err)

	// check values
	values, err := client.GetUserValues(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&AttrUserValuesRequest{
			ItemId:   itemID,
			Language: "en",
		},
	)
	require.NoError(t, err)

	index := slices.IndexFunc(values.GetItems(), func(value *AttrUserValue) bool {
		return value.GetAttributeId() == schema.EngineTypeAttr
	})
	require.NotEqual(t, -1, index)
	require.True(t, values.GetItems()[index].GetValue().GetIsEmpty())
}

func TestChart(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	client := NewAttrsClient(conn)

	_, err := client.GetChartParameters(
		ctx,
		&emptypb.Empty{},
	)
	require.NoError(t, err)

	_, err = client.GetChartData(
		ctx,
		&ChartDataRequest{Id: schema.LengthAttr},
	)
	require.NoError(t, err)
}
