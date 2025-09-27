package goautowp

import (
	"fmt"
	"math/rand"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/schema"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/metadata"
)

func TestMostsMenu(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	client := NewMostsClient(conn)

	res, err := client.GetMenu(ctx, &MostsMenuRequest{})
	require.NoError(t, err)
	require.NotEmpty(t, res.GetYears())
	require.NotEmpty(t, res.GetVehicleTypes())
	require.NotEmpty(t, res.GetRatings())
}

// nolint: dupl, nolintlint
func TestMostsRatings(t *testing.T) { //nolint: maintidx
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	client := NewMostsClient(conn)
	itemsClient := NewItemsClient(conn)
	attrsClient := NewAttrsClient(conn)

	// admin
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

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	tests := []struct {
		ratingCatname string
		values1       []*AttrUserValue
		values2       []*AttrUserValue
		value1        string
		value2        string
	}{
		{
			ratingCatname: "dynamic",
			values1: []*AttrUserValue{
				{
					AttributeId: schema.AccelerationTo100KmhAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 1,
					},
				},
			},
			values2: []*AttrUserValue{
				{
					AttributeId: schema.AccelerationTo60MphAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 10,
					},
				},
			},
			value1: `1.0 <span class="unit">сек до&#xa0;100&#xa0;км/ч</span>`,
			value2: `10.0 <span class="unit">сек до&#xa0;60&#xa0;миль/ч</span>`,
		},
		{
			ratingCatname: "static",
			values1: []*AttrUserValue{
				{
					AttributeId: schema.AccelerationTo100KmhAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 10,
					},
				},
			},
			values2: []*AttrUserValue{
				{
					AttributeId: schema.AccelerationTo60MphAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 1,
					},
				},
			},
			value1: `10.0 <span class="unit">сек до&#xa0;100&#xa0;км/ч</span>`,
			value2: `1.0 <span class="unit">сек до&#xa0;60&#xa0;миль/ч</span>`,
		},
		{
			ratingCatname: "fastest",
			values1: []*AttrUserValue{
				{
					AttributeId: schema.MaxSpeedAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 300,
					},
				},
			},
			values2: []*AttrUserValue{
				{
					AttributeId: schema.MaxSpeedAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 150,
					},
				},
			},
			value1: `300.0`,
			value2: `150.0`,
		},
		{
			ratingCatname: "slowest",
			values1: []*AttrUserValue{
				{
					AttributeId: schema.MaxSpeedAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 150,
					},
				},
			},
			values2: []*AttrUserValue{
				{
					AttributeId: schema.MaxSpeedAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 300,
					},
				},
			},
			value1: `150.0`,
			value2: `300.0`,
		},
		{
			ratingCatname: "big-engine",
			values1: []*AttrUserValue{
				{
					AttributeId: schema.EngineVolumeAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_FLOAT,
						Valid:    true,
						IntValue: 6000,
					},
				},
			},
			values2: []*AttrUserValue{
				{
					AttributeId: schema.EngineVolumeAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_FLOAT,
						Valid:    true,
						IntValue: 1500,
					},
				},
			},
			value1: `6,000`,
			value2: `1,500`,
		},
		{
			ratingCatname: "small-engine",
			values1: []*AttrUserValue{
				{
					AttributeId: schema.EngineVolumeAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_FLOAT,
						Valid:    true,
						IntValue: 1500,
					},
				},
			},
			values2: []*AttrUserValue{
				{
					AttributeId: schema.EngineVolumeAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_FLOAT,
						Valid:    true,
						IntValue: 6000,
					},
				},
			},
			value1: `1,500`,
			value2: `6,000`,
		},
		{
			ratingCatname: "bigbrakes",
			values1: []*AttrUserValue{
				{
					AttributeId: schema.RearBrakesDiameterAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 135,
					},
				},
				{
					AttributeId: schema.RearBrakesThicknessAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 30,
					},
				},
				{
					AttributeId: schema.FrontBrakesDiameterAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 135,
					},
				},
				{
					AttributeId: schema.FrontBrakesThicknessAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 30,
					},
				},
			},
			values2: []*AttrUserValue{
				{
					AttributeId: schema.RearBrakesDiameterAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 100,
					},
				},
				{
					AttributeId: schema.RearBrakesThicknessAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 30,
					},
				},
				{
					AttributeId: schema.FrontBrakesDiameterAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 100,
					},
				},
				{
					AttributeId: schema.FrontBrakesThicknessAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 30,
					},
				},
			},
			value1: `135 × 30 <span class="unit">мм</span>`,
			value2: `100 × 30 <span class="unit">мм</span>`,
		},
		{
			ratingCatname: "smallbrakes",
			values1: []*AttrUserValue{
				{
					AttributeId: schema.RearBrakesDiameterAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 100,
					},
				},
				{
					AttributeId: schema.RearBrakesThicknessAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 30,
					},
				},
				{
					AttributeId: schema.FrontBrakesDiameterAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 100,
					},
				},
				{
					AttributeId: schema.FrontBrakesThicknessAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 30,
					},
				},
			},
			values2: []*AttrUserValue{
				{
					AttributeId: schema.RearBrakesDiameterAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 135,
					},
				},
				{
					AttributeId: schema.RearBrakesThicknessAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 30,
					},
				},
				{
					AttributeId: schema.FrontBrakesDiameterAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 135,
					},
				},
				{
					AttributeId: schema.FrontBrakesThicknessAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 30,
					},
				},
			},
			value1: `100 × 30 <span class="unit">мм</span>`,
			value2: `135 × 30 <span class="unit">мм</span>`,
		},
		{
			ratingCatname: "mighty",
			values1: []*AttrUserValue{
				{
					AttributeId: schema.EnginePowerAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 200,
					},
				},
				{
					AttributeId: schema.EngineConfigurationCylindersLayoutAttr,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_LIST,
						Valid:     true,
						ListValue: []int64{10},
					},
				},
				{
					AttributeId: schema.EngineConfigurationCylindersCountAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 6,
					},
				},
				{
					AttributeId: schema.EngineConfigurationValvesCountAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 4,
					},
				},
				{
					AttributeId: schema.EngineTurboAttr,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_LIST,
						Valid:     true,
						ListValue: []int64{48},
					},
				},
				{
					AttributeId: schema.EngineVolumeAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 6000,
					},
				},
			},
			values2: []*AttrUserValue{
				{
					AttributeId: schema.EnginePowerAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 100,
					},
				},
				{
					AttributeId: schema.EngineConfigurationCylindersLayoutAttr,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_LIST,
						Valid:     true,
						ListValue: []int64{10},
					},
				},
				{
					AttributeId: schema.EngineConfigurationCylindersCountAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 6,
					},
				},
				{
					AttributeId: schema.EngineConfigurationValvesCountAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 4,
					},
				},
				{
					AttributeId: schema.EngineTurboAttr,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_LIST,
						Valid:     true,
						ListValue: []int64{48},
					},
				},
				{
					AttributeId: schema.EngineVolumeAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 6000,
					},
				},
			},
			value1: `200 <span class="unit">л.с.</span><p class="note">O6/4, ` +
				`6.0 <span class="unit">л</span>, турбонаддув ×2</p>`,
			value2: `100 <span class="unit">л.с.</span><p class="note">O6/4, ` +
				`6.0 <span class="unit">л</span>, турбонаддув ×2</p>`,
		},
		{ //nolint: dupl
			ratingCatname: "weak",
			values1: []*AttrUserValue{
				{
					AttributeId: schema.EnginePowerAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 100,
					},
				},
				{
					AttributeId: schema.EngineConfigurationCylindersLayoutAttr,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_LIST,
						Valid:     true,
						ListValue: []int64{10},
					},
				},
				{
					AttributeId: schema.EngineConfigurationCylindersCountAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 6,
					},
				},
				{
					AttributeId: schema.EngineConfigurationValvesCountAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 4,
					},
				},
				{
					AttributeId: schema.EngineTurboAttr,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_LIST,
						Valid:     true,
						ListValue: []int64{48},
					},
				},
				{
					AttributeId: schema.EngineVolumeAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 6000,
					},
				},
			},
			values2: []*AttrUserValue{
				{
					AttributeId: schema.EnginePowerAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 200,
					},
				},
				{
					AttributeId: schema.EngineConfigurationCylindersLayoutAttr,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_LIST,
						Valid:     true,
						ListValue: []int64{10},
					},
				},
				{
					AttributeId: schema.EngineConfigurationCylindersCountAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 6,
					},
				},
				{
					AttributeId: schema.EngineConfigurationValvesCountAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 4,
					},
				},
				{
					AttributeId: schema.EngineTurboAttr,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_LIST,
						Valid:     true,
						ListValue: []int64{48},
					},
				},
				{
					AttributeId: schema.EngineVolumeAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 6000,
					},
				},
			},
			value1: `100 <span class="unit">л.с.</span><p class="note">O6/4, ` +
				`6.0 <span class="unit">л</span>, турбонаддув ×2</p>`,
			value2: `200 <span class="unit">л.с.</span><p class="note">O6/4, ` +
				`6.0 <span class="unit">л</span>, турбонаддув ×2</p>`,
		},
		{
			ratingCatname: "bigwheel",
			values1: []*AttrUserValue{
				{
					AttributeId: schema.FrontWheelTyreWidthAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 175,
					},
				},
				{
					AttributeId: schema.FrontWheelTyreSeriesAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 75,
					},
				},
				{
					AttributeId: schema.FrontWheelRadiusAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 15.5,
					},
				},
				{
					AttributeId: schema.RearWheelTyreWidthAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 225,
					},
				},
				{
					AttributeId: schema.RearWheelTyreSeriesAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 75,
					},
				},
				{
					AttributeId: schema.RearWheelRadiusAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 15.5,
					},
				},
			},
			values2: []*AttrUserValue{
				{
					AttributeId: schema.FrontWheelTyreWidthAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 175,
					},
				},
				{
					AttributeId: schema.FrontWheelTyreSeriesAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 75,
					},
				},
				{
					AttributeId: schema.FrontWheelRadiusAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 15.5,
					},
				},
				{
					AttributeId: schema.RearWheelTyreWidthAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 175,
					},
				},
				{
					AttributeId: schema.RearWheelTyreSeriesAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 75,
					},
				},
				{
					AttributeId: schema.RearWheelRadiusAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 15.5,
					},
				},
			},
			value1: `175/75 R15.5<br />225/75 R15.5`,
			value2: `175/75 R15.5`,
		},
		{
			ratingCatname: "smallwheel",
			values1: []*AttrUserValue{
				{
					AttributeId: schema.FrontWheelTyreWidthAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 175,
					},
				},
				{
					AttributeId: schema.FrontWheelTyreSeriesAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 75,
					},
				},
				{
					AttributeId: schema.FrontWheelRadiusAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 15.5,
					},
				},
				{
					AttributeId: schema.RearWheelTyreWidthAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 175,
					},
				},
				{
					AttributeId: schema.RearWheelTyreSeriesAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 75,
					},
				},
				{
					AttributeId: schema.RearWheelRadiusAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 15.5,
					},
				},
			},
			values2: []*AttrUserValue{
				{
					AttributeId: schema.FrontWheelTyreWidthAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 175,
					},
				},
				{
					AttributeId: schema.FrontWheelTyreSeriesAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 75,
					},
				},
				{
					AttributeId: schema.FrontWheelRadiusAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 15.5,
					},
				},
				{
					AttributeId: schema.RearWheelTyreWidthAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 225,
					},
				},
				{
					AttributeId: schema.RearWheelTyreSeriesAttr,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 75,
					},
				},
				{
					AttributeId: schema.RearWheelRadiusAttr,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 15.5,
					},
				},
			},
			value1: `175/75 R15.5`,
			value2: `175/75 R15.5<br />225/75 R15.5`,
		},
	}

	for _, tt := range tests {
		t.Run(tt.ratingCatname, func(t *testing.T) {
			t.Parallel()

			randomInt := random.Int()

			brandID := createItem(t, conn, cnt, &APIItem{ //nolint: contextcheck
				Name:       fmt.Sprintf("brand-%d", randomInt),
				IsGroup:    true,
				ItemTypeId: ItemType_ITEM_TYPE_BRAND,
				Catname:    fmt.Sprintf("brand-%d", randomInt),
			})

			vehicle1ID := createItem(t, conn, cnt, &APIItem{ //nolint: contextcheck
				Name:       fmt.Sprintf("vehicle1-%d", randomInt),
				IsGroup:    true,
				ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
				BeginYear:  1930,
				EndYear:    1944,
			})

			// second vehicle
			vehicle2ID := createItem(t, conn, cnt, &APIItem{ //nolint: contextcheck
				Name:       fmt.Sprintf("vehicle2-%d", randomInt),
				IsGroup:    true,
				ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
				BeginYear:  1920,
				EndYear:    1950,
			})

			_, err = itemsClient.CreateItemParent(
				metadata.AppendToOutgoingContext(
					ctx,
					authorizationHeader,
					bearerPrefix+adminToken.AccessToken,
				),
				&ItemParent{
					ItemId: vehicle1ID, ParentId: brandID, Catname: fmt.Sprintf("vehicle1-%d", randomInt),
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
					ItemId: vehicle2ID, ParentId: brandID, Catname: fmt.Sprintf("vehicle2-%d", randomInt),
				},
			)
			require.NoError(t, err)

			for id := range tt.values1 {
				tt.values1[id].ItemId = vehicle1ID
			}

			_, err = attrsClient.SetUserValues(
				metadata.AppendToOutgoingContext(
					ctx,
					authorizationHeader,
					bearerPrefix+adminToken.AccessToken,
				),
				&AttrSetUserValuesRequest{Items: tt.values1},
			)
			require.NoError(t, err)

			for id := range tt.values2 {
				tt.values2[id].ItemId = vehicle2ID
			}

			_, err = attrsClient.SetUserValues(
				metadata.AppendToOutgoingContext(
					ctx,
					authorizationHeader,
					bearerPrefix+adminToken.AccessToken,
				),
				&AttrSetUserValuesRequest{Items: tt.values2},
			)
			require.NoError(t, err)

			// rating
			res, err := client.GetItems(ctx, &MostsItemsRequest{
				Language:      "en",
				YearsCatname:  "1930-39",
				RatingCatname: tt.ratingCatname,
				BrandId:       brandID,
			})
			require.NoError(t, err)
			require.NotEmpty(t, res.GetItems(), "item %d not found with years filter", vehicle1ID)
			require.Len(t, res.GetItems(), 2)
			require.Equal(t, vehicle1ID, res.GetItems()[0].GetItem().GetId())
			require.Equal(t, vehicle2ID, res.GetItems()[1].GetItem().GetId())
			require.Equal(t, tt.value1, res.GetItems()[0].GetValueHtml())
			require.Equal(t, tt.value2, res.GetItems()[1].GetValueHtml())
		})
	}
}
