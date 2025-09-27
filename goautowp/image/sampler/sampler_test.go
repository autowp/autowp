package sampler

import (
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/stretchr/testify/require"
	"gopkg.in/gographics/imagick.v3/imagick"
)

const towerFilePath = "./_files/Towers_Schiphol_small.jpg"

func TestFormat(t *testing.T) { //nolint:maintidx
	t.Parallel()

	tests := []struct {
		name         string
		formatConfig config.ImageStorageSamplerFormatConfig
		width        int
		height       int
	}{
		{
			name: "ShouldResizeOddWidthPictureStrictlyToTargetWidthByOuterFitType",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				FitType:    config.FitTypeOuter,
				Width:      102,
				Height:     149,
				Background: "red",
			},
			width:  102,
			height: 149,
		},
		{
			name: "ShouldResizeOddHeightPictureStrictlyToTargetHeightByOuterFitType",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				FitType:    config.FitTypeOuter,
				Width:      101,
				Height:     150,
				Background: "red",
			},
			width:  101,
			height: 150,
		},
		{
			name: "FitTypeInner: both size less",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				FitType:    config.FitTypeInner,
				Width:      150,
				Height:     200,
				ReduceOnly: true,
			},
			width:  101,
			height: 149,
		},
		{
			name: "FitTypeInner: height less",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				FitType:    config.FitTypeInner,
				Width:      50,
				Height:     200,
				ReduceOnly: true,
			},
			width:  50,
			height: 74,
		},
		{
			name: "FitTypeInner: not less",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				FitType:    config.FitTypeInner,
				Width:      50,
				Height:     100,
				ReduceOnly: true,
			},
			width:  50,
			height: 100,
		},
		{
			name: "FitTypeInner: both size less, reduceOnly off",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				FitType:    config.FitTypeInner,
				Width:      150,
				Height:     200,
				ReduceOnly: false,
			},
			width:  150,
			height: 200,
		},
		{
			name: "FitTypeInner: width less, reduceOnly off",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				FitType:    config.FitTypeInner,
				Width:      150,
				Height:     100,
				ReduceOnly: false,
			},
			width:  150,
			height: 100,
		},
		{
			name: "FitTypeInner: height less, reduceOnly off",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				FitType:    config.FitTypeInner,
				Width:      50,
				Height:     200,
				ReduceOnly: false,
			},
			width:  50,
			height: 200,
		},
		{
			name: "FitTypeInner: not less, reduceOnly off",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				FitType:    config.FitTypeInner,
				Width:      50,
				Height:     100,
				ReduceOnly: false,
			},
			width:  50,
			height: 100,
		},
		{
			name: "FitTypeOuter: both size less",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				FitType:    config.FitTypeOuter,
				Width:      150,
				Height:     200,
				ReduceOnly: true,
			},
			width:  150,
			height: 200,
		},
		{
			name: "FitTypeOuter: width less",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				FitType:    config.FitTypeOuter,
				Width:      150,
				Height:     100,
				ReduceOnly: true,
			},
			width:  150,
			height: 100,
		},
		{
			name: "FitTypeOuter: height less",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				FitType:    config.FitTypeOuter,
				Width:      50,
				Height:     200,
				ReduceOnly: true,
			},
			width:  50,
			height: 200,
		},
		{
			name: "FitTypeOuter: not less",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				FitType:    config.FitTypeOuter,
				Width:      50,
				Height:     100,
				ReduceOnly: true,
			},
			width:  50,
			height: 100,
		},
		{
			name: "FitTypeOuter: both size less, reduceOnly off",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				FitType:    config.FitTypeOuter,
				Width:      150,
				Height:     200,
				ReduceOnly: false,
			},
			width:  150,
			height: 200,
		},
		{
			name: "FitTypeOuter: width less, reduceOnly off",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				FitType:    config.FitTypeOuter,
				Width:      150,
				Height:     100,
				ReduceOnly: false,
			},
			width:  150,
			height: 100,
		},
		{
			name: "FitTypeOuter: height less, reduceOnly off",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				FitType:    config.FitTypeOuter,
				Width:      50,
				Height:     200,
				ReduceOnly: false,
			},
			width:  50,
			height: 200,
		},
		{
			name: "FitTypeOuter: not less, reduceOnly off",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				FitType:    config.FitTypeOuter,
				Width:      50,
				Height:     100,
				ReduceOnly: false,
			},
			width:  50,
			height: 100,
		},
		//
		{
			name: "MaximumFit: both size less",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				FitType:    config.FitTypeMaximum,
				Width:      150,
				Height:     200,
				ReduceOnly: true,
			},
			width:  101,
			height: 149,
		},
		{
			name: "MaximumFit: width less",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				FitType:    config.FitTypeMaximum,
				Width:      150,
				Height:     100,
				ReduceOnly: true,
			},
			width:  68,
			height: 100,
		},
		{
			name: "MaximumFit: height less",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				FitType:    config.FitTypeMaximum,
				Width:      50,
				Height:     200,
				ReduceOnly: true,
			},
			width:  50,
			height: 74,
		},
		{
			name: "MaximumFit: not less",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				FitType:    config.FitTypeMaximum,
				Width:      50,
				Height:     100,
				ReduceOnly: true,
			},
			width:  50,
			height: 74,
		},
		{
			name: "MaximumFit: both size less, reduceOnly off",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				FitType:    config.FitTypeMaximum,
				Width:      150,
				Height:     200,
				ReduceOnly: false,
			},
			width:  136,
			height: 200,
		},
		{
			name: "MaximumFit: width less, reduceOnly off",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				FitType:    config.FitTypeMaximum,
				Width:      150,
				Height:     100,
				ReduceOnly: false,
			},
			width:  68,
			height: 100,
		},
		{
			name: "MaximumFit: height less, reduceOnly off",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				FitType:    config.FitTypeMaximum,
				Width:      50,
				Height:     200,
				ReduceOnly: false,
			},
			width:  50,
			height: 74,
		},
		{
			name: "MaximumFit: not less, reduceOnly off",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				FitType:    config.FitTypeMaximum,
				Width:      50,
				Height:     100,
				ReduceOnly: false,
			},
			width:  50,
			height: 74,
		},
		{
			name: "ReduceOnlyByWidth: width less",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				Width:      150,
				ReduceOnly: true,
			},
			width:  101,
			height: 149,
		},
		{
			name: "ReduceOnlyByWidth: not less",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				Width:      50,
				ReduceOnly: true,
			},
			width:  50,
			height: 74,
		},
		{
			name: "ReduceOnlyByWidth: width less, reduceOnly off",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				Width:      150,
				ReduceOnly: false,
			},
			width:  150,
			height: 221,
		},
		{
			name: "ReduceOnlyByWidth: not less, reduceOnly off",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				Width:      50,
				ReduceOnly: false,
			},
			width:  50,
			height: 74,
		},
		{
			name: "ReduceOnlyByHeight: height less",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				Height:     200,
				ReduceOnly: true,
			},
			width:  101,
			height: 149,
		},
		{
			name: "ReduceOnlyByHeight: not less",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				Height:     100,
				ReduceOnly: true,
			},
			width:  68,
			height: 100,
		},
		{
			name: "ReduceOnlyByHeight: height less, reduceOnly off",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				Height:     200,
				ReduceOnly: false,
			},
			width:  136,
			height: 200,
		},
		{
			name: "ReduceOnlyByHeight: not less, reduceOnly off",
			formatConfig: config.ImageStorageSamplerFormatConfig{
				Height:     100,
				ReduceOnly: false,
			},
			width:  68,
			height: 100,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			sampler := NewSampler()
			mw := imagick.NewMagickWand()

			defer mw.Destroy()

			err := mw.ReadImage(towerFilePath)
			require.NoError(t, err)

			format := NewFormat(tt.formatConfig)
			mw, err = sampler.ConvertImage(mw, Crop{}, *format)
			require.NoError(t, err)
			require.EqualValues(t, tt.width, mw.GetImageWidth())
			require.EqualValues(t, tt.height, mw.GetImageHeight())
			mw.Clear()
		})
	}
}

func TestAnimationPreservedDueResample(t *testing.T) {
	t.Parallel()

	mw := imagick.NewMagickWand()
	defer mw.Destroy()

	err := mw.ReadImage("./_files/icon-animation.gif")
	require.NoError(t, err)

	sampler := NewSampler()

	format := NewFormat(config.ImageStorageSamplerFormatConfig{
		FitType: config.FitTypeInner,
		Width:   200,
		Height:  200,
	})
	mw, err = sampler.ConvertImage(mw, Crop{}, *format)
	require.NoError(t, err)

	require.Less(t, uint(1), mw.GetNumberImages())

	require.EqualValues(t, 200, mw.GetImageWidth())
	require.EqualValues(t, 200, mw.GetImageHeight())

	mw.Clear()
}

func TestResizeGif(t *testing.T) {
	t.Parallel()

	mw := imagick.NewMagickWand()
	defer mw.Destroy()

	err := mw.ReadImage("./_files/rudolp-jumping-rope.gif")
	require.NoError(t, err)

	sampler := NewSampler()

	format := NewFormat(config.ImageStorageSamplerFormatConfig{
		FitType:    config.FitTypeInner,
		Width:      80,
		Height:     80,
		Background: "transparent",
	})
	mw, err = sampler.ConvertImage(mw, Crop{}, *format)
	require.NoError(t, err)

	require.Less(t, uint(1), mw.GetNumberImages())

	require.EqualValues(t, 80, mw.GetImageWidth())
	require.EqualValues(t, 80, mw.GetImageHeight())

	mw.Clear()
}

func TestResizeGifWithProportionsConstraints(t *testing.T) {
	t.Parallel()

	mw := imagick.NewMagickWand()
	defer mw.Destroy()

	err := mw.ReadImage("./_files/rudolp-jumping-rope.gif")
	require.NoError(t, err)

	sampler := NewSampler()

	format := NewFormat(config.ImageStorageSamplerFormatConfig{
		FitType:    config.FitTypeInner,
		Width:      456,
		Background: "",
		Widest:     16.0 / 9.0,
		Highest:    9.0 / 16.0,
		ReduceOnly: true,
	})
	mw, err = sampler.ConvertImage(mw, Crop{}, *format)
	require.NoError(t, err)

	require.Less(t, uint(1), mw.GetNumberImages())

	require.EqualValues(t, 456, mw.GetImageWidth())
	require.EqualValues(t, 342, mw.GetImageHeight())

	mw.Clear()
}

func TestVerticalProportional(t *testing.T) {
	t.Parallel()

	sampler := NewSampler()

	mw := imagick.NewMagickWand()
	defer mw.Destroy()
	// both size less
	err := mw.ReadImage("./_files/mazda3_sedan_us-spec_11.jpg")
	require.NoError(t, err)

	format := NewFormat(config.ImageStorageSamplerFormatConfig{
		FitType:          config.FitTypeInner,
		Width:            200,
		Height:           200,
		ReduceOnly:       true,
		ProportionalCrop: true,
	})
	mw, err = sampler.ConvertImage(mw, Crop{}, *format)
	require.NoError(t, err)

	require.EqualValues(t, 200, mw.GetImageWidth())
	require.EqualValues(t, 200, mw.GetImageHeight())
	mw.Clear()
}

func TestHorizontalProportional(t *testing.T) {
	t.Parallel()

	sampler := NewSampler()

	mw := imagick.NewMagickWand()
	defer mw.Destroy()

	// both size less
	err := mw.ReadImage("./_files/mazda3_sedan_us-spec_11.jpg")
	require.NoError(t, err)

	format := NewFormat(config.ImageStorageSamplerFormatConfig{
		FitType:          config.FitTypeInner,
		Width:            400,
		Height:           200,
		ReduceOnly:       true,
		ProportionalCrop: true,
	})
	mw, err = sampler.ConvertImage(mw, Crop{}, *format)
	require.NoError(t, err)

	require.EqualValues(t, 400, mw.GetImageWidth())
	require.EqualValues(t, 200, mw.GetImageHeight())
	mw.Clear()
}

func TestWidest(t *testing.T) {
	t.Parallel()

	sampler := NewSampler()

	mw := imagick.NewMagickWand()
	defer mw.Destroy()
	// height less
	err := mw.ReadImage("./_files/wide-image.png") // 1000x229
	require.NoError(t, err)

	format := NewFormat(config.ImageStorageSamplerFormatConfig{
		Widest: 4.0 / 3.0,
	})
	mw, err = sampler.ConvertImage(mw, Crop{}, *format)
	require.NoError(t, err)
	require.EqualValues(t, 305, mw.GetImageWidth())
	require.EqualValues(t, 229, mw.GetImageHeight())
	mw.Clear()
}

func TestHighest(t *testing.T) {
	t.Parallel()

	sampler := NewSampler()

	mw := imagick.NewMagickWand()
	defer mw.Destroy()
	// height less
	err := mw.ReadImage(towerFilePath) // 101x149
	require.NoError(t, err)

	format := NewFormat(config.ImageStorageSamplerFormatConfig{
		Highest: 1.0,
	})
	mw, err = sampler.ConvertImage(mw, Crop{}, *format)
	require.NoError(t, err)
	require.EqualValues(t, 101, mw.GetImageWidth())
	require.EqualValues(t, 101, mw.GetImageHeight())
	mw.Clear()
}

func TestPngAvatar(t *testing.T) {
	t.Parallel()

	sampler := NewSampler()

	mw := imagick.NewMagickWand()
	defer mw.Destroy()
	// height less
	err := mw.ReadImage("./_files/test.png")
	require.NoError(t, err)

	defer mw.Clear()

	format := NewFormat(config.ImageStorageSamplerFormatConfig{
		Width:      70,
		Height:     70,
		Background: "transparent",
		Strip:      true,
	})
	mw, err = sampler.ConvertImage(mw, Crop{}, *format)
	require.NoError(t, err)
	require.EqualValues(t, 70, mw.GetImageWidth())
	require.EqualValues(t, 70, mw.GetImageHeight())
}
