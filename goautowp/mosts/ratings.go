package mosts

import "github.com/autowp/goautowp/schema"

type RatingAdapter struct {
	Name      string
	Attribute int64
	Order     string
}

type Rating struct {
	CatName string
	Adapter Adapter
}

var ratings = []Rating{
	{
		CatName: "fastest",
		Adapter: Attr{
			Attribute: schema.MaxSpeedAttr,
			OrderAsc:  false,
		},
	},
	{
		CatName: "slowest",
		Adapter: Attr{
			Attribute: schema.MaxSpeedAttr,
			OrderAsc:  true,
		},
	},
	{
		CatName: "dynamic",
		Adapter: Acceleration{
			To100kmhAttribute: schema.AccelerationTo100KmhAttr,
			To60mphAttribute:  schema.AccelerationTo60MphAttr,
			OrderAsc:          true,
		},
	},
	{
		CatName: "static",
		Adapter: Acceleration{
			To100kmhAttribute: schema.AccelerationTo100KmhAttr,
			To60mphAttribute:  schema.AccelerationTo60MphAttr,
			OrderAsc:          false,
		},
	},
	{
		CatName: "mighty",
		Adapter: Power{
			PowerAttribute:            schema.EnginePowerAttr,
			CylindersLayoutAttribute:  schema.EngineConfigurationCylindersLayoutAttr,
			CylindersCountAttribute:   schema.EngineConfigurationCylindersCountAttr,
			ValvePerCylinderAttribute: schema.EngineConfigurationValvesCountAttr,
			TurboAttribute:            schema.EngineTurboAttr,
			VolumeAttribute:           schema.EngineVolumeAttr,
			OrderAsc:                  false,
		},
	},
	{
		CatName: "weak",
		Adapter: Power{
			PowerAttribute:            schema.EnginePowerAttr,
			CylindersLayoutAttribute:  schema.EngineConfigurationCylindersLayoutAttr,
			CylindersCountAttribute:   schema.EngineConfigurationCylindersCountAttr,
			ValvePerCylinderAttribute: schema.EngineConfigurationValvesCountAttr,
			TurboAttribute:            schema.EngineTurboAttr,
			VolumeAttribute:           schema.EngineVolumeAttr,
			OrderAsc:                  true,
		},
	},
	{
		CatName: "big-engine",
		Adapter: Attr{
			Attribute: schema.EngineVolumeAttr,
			OrderAsc:  false,
		},
	},
	{
		CatName: "small-engine",
		Adapter: Attr{
			Attribute: schema.EngineVolumeAttr,
			OrderAsc:  true,
		},
	},
	{
		CatName: "nimblest",
		Adapter: Attr{
			Attribute: schema.TurningDiameterAttr,
			OrderAsc:  true,
		},
	},
	{
		CatName: "economical",
		Adapter: Attr{
			Attribute: schema.FuelConsumptionMixedAttr,
			OrderAsc:  true,
		},
	},
	{
		CatName: "gluttonous",
		Adapter: Attr{
			Attribute: schema.FuelConsumptionMixedAttr,
			OrderAsc:  false,
		},
	},
	{
		CatName: "clenaly",
		Adapter: Attr{
			Attribute: schema.EmissionsAttr,
			OrderAsc:  true,
		},
	},
	{
		CatName: "dirty",
		Adapter: Attr{
			Attribute: schema.EmissionsAttr,
			OrderAsc:  false,
		},
	},
	{
		CatName: "heavy",
		Adapter: Attr{
			Attribute: schema.CurbWeightAttr,
			OrderAsc:  false,
		},
	},
	{
		CatName: "lightest",
		Adapter: Attr{
			Attribute: schema.CurbWeightAttr,
			OrderAsc:  true,
		},
	},
	{
		CatName: "longest",
		Adapter: Attr{
			Attribute: schema.LengthAttr,
			OrderAsc:  false,
		},
	},
	{
		CatName: "shortest",
		Adapter: Attr{
			Attribute: schema.LengthAttr,
			OrderAsc:  true,
		},
	},
	{
		CatName: "widest",
		Adapter: Attr{
			Attribute: schema.WidthAttr,
			OrderAsc:  false,
		},
	},
	{
		CatName: "narrow",
		Adapter: Attr{
			Attribute: schema.WidthAttr,
			OrderAsc:  true,
		},
	},
	{
		CatName: "highest",
		Adapter: Attr{
			Attribute: schema.HeightAttr,
			OrderAsc:  false,
		},
	},
	{
		CatName: "lowest",
		Adapter: Attr{
			Attribute: schema.HeightAttr,
			OrderAsc:  true,
		},
	},
	{
		CatName: "air",
		Adapter: Attr{
			Attribute: schema.AirResistanceFrontal,
			OrderAsc:  true,
		},
	},
	{
		CatName: "antiair",
		Adapter: Attr{
			Attribute: schema.AirResistanceFrontal,
			OrderAsc:  false,
		},
	},
	{
		CatName: "bigwheel",
		Adapter: Wheelsize{
			OrderAsc: false,
			Rear: WheelAxis{
				Tyrewidth:  schema.RearWheelTyreWidthAttr,
				Tyreseries: schema.RearWheelTyreSeriesAttr,
				Radius:     schema.RearWheelRadiusAttr,
			},
			Front: WheelAxis{
				Tyrewidth:  schema.FrontWheelTyreWidthAttr,
				Tyreseries: schema.FrontWheelTyreSeriesAttr,
				Radius:     schema.FrontWheelRadiusAttr,
			},
		},
	},
	{
		CatName: "smallwheel",
		Adapter: Wheelsize{
			OrderAsc: true,
			Rear: WheelAxis{
				Tyrewidth:  schema.RearWheelTyreWidthAttr,
				Tyreseries: schema.RearWheelTyreSeriesAttr,
				Radius:     schema.RearWheelRadiusAttr,
			},
			Front: WheelAxis{
				Tyrewidth:  schema.FrontWheelTyreWidthAttr,
				Tyreseries: schema.FrontWheelTyreSeriesAttr,
				Radius:     schema.FrontWheelRadiusAttr,
			},
		},
	},
	{
		CatName: "bigbrakes",
		Adapter: Brakes{
			OrderAsc: false,
			Rear: BrakesAxis{
				Diameter:  schema.RearBrakesDiameterAttr,
				Thickness: schema.RearBrakesThicknessAttr,
			},
			Front: BrakesAxis{
				Diameter:  schema.FrontBrakesDiameterAttr,
				Thickness: schema.FrontBrakesThicknessAttr,
			},
		},
	},
	{
		CatName: "smallbrakes",
		Adapter: Brakes{
			OrderAsc: true,
			Rear: BrakesAxis{
				Diameter:  schema.RearBrakesDiameterAttr,
				Thickness: schema.RearBrakesThicknessAttr,
			},
			Front: BrakesAxis{
				Diameter:  schema.FrontBrakesDiameterAttr,
				Thickness: schema.FrontBrakesThicknessAttr,
			},
		},
	},
	{
		CatName: "bigclearance",
		Adapter: Attr{
			Attribute: schema.ClearanceAttr,
			OrderAsc:  false,
		},
	},
	{
		CatName: "smallclearance",
		Adapter: Attr{
			Attribute: schema.ClearanceAttr,
			OrderAsc:  true,
		},
	},
}
