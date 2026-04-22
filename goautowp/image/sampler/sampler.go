package sampler

import (
	"errors"
	"fmt"
	"math"

	"github.com/autowp/goautowp/config"
	"gopkg.in/gographics/imagick.v3/imagick"
)

const rationComparePrecision = 0.001

var (
	errMissingCropParameters = errors.New("crop parameters not properly set")
	errCropHeightOutOfBounds = errors.New("crop height out of bounds")
	errCropWidthOutOfBounds  = errors.New("crop width out of bounds")
	errCropLeftOutOfBounds   = errors.New("crop left out of bounds")
	errCropTopOutOfBounds    = errors.New("crop top out of bounds")
	errUnexpectedFitType     = errors.New("unexpected FIT_TYPE")
)

type Sampler struct{}

func NewSampler() *Sampler {
	return &Sampler{}
}

func (s Sampler) ConvertImage(
	mw *imagick.MagickWand,
	crop Crop,
	format Format,
) (*imagick.MagickWand, error) {
	if !crop.IsEmpty() && !format.IsIgnoreCrop() {
		err := s.cropImage(mw, crop, format)
		if err != nil {
			return nil, err
		}
	}

	decomposed := mw
	if mw.GetImageFormat() == ImagickFormatGIF {
		decomposed = mw.CoalesceImages()
		mw.Destroy()
	}

	// fit by widest
	if widest := format.Widest(); widest > 0 {
		err := s.cropToWidest(decomposed, widest)
		if err != nil {
			return nil, err
		}
	}

	// fit by highest
	if highest := format.Highest(); highest > 0 {
		err := s.cropToHighest(decomposed, highest)
		if err != nil {
			return nil, err
		}
	}

	// check for monotone background extend possibility
	fWidth := format.Width()
	fHeight := format.Height()

	if format.IsProportionalCrop() && fWidth > 0 && fHeight > 0 {
		fRatio := float64(fWidth) / float64(fHeight)
		cRatio := float64(decomposed.GetImageWidth()) / float64(decomposed.GetImageHeight())

		ratioDiff := math.Abs(fRatio - cRatio)

		if ratioDiff > rationComparePrecision {
			if cRatio > fRatio {
				err := s.extendVertical(decomposed, format)
				if err != nil {
					return nil, err
				}
			} else {
				err := s.extendHorizontal(decomposed, format)
				if err != nil {
					return nil, err
				}
			}
		}
	}

	background := format.Background()
	if background != "" {
		pw := imagick.NewPixelWand()
		defer pw.Destroy()

		pw.SetColor(background)

		err := decomposed.SetBackgroundColor(pw)
		if err != nil {
			return nil, err
		}

		err = decomposed.SetImageBackgroundColor(pw)
		if err != nil {
			return nil, err
		}
	}

	if fWidth > 0 && fHeight > 0 {
		switch format.FitType() {
		case config.FitTypeInner:
			err := s.convertByInnerFit(decomposed, format)
			if err != nil {
				return nil, err
			}
		case config.FitTypeOuter:
			err := s.convertByOuterFit(decomposed, format)
			if err != nil {
				return nil, err
			}
		case config.FitTypeMaximum:
			err := s.convertByMaximumFit(decomposed, format)
			if err != nil {
				return nil, err
			}
		default:
			return nil, fmt.Errorf("%w: `%v`", errUnexpectedFitType, format.FitType())
		}
	} else {
		if fWidth > 0 {
			err := s.convertByWidth(decomposed, format)
			if err != nil {
				return nil, err
			}
		} else if fHeight > 0 {
			err := s.convertByHeight(decomposed, format)
			if err != nil {
				return nil, err
			}
		}
	}

	mw = decomposed

	if decomposed.GetImageFormat() == ImagickFormatGIF {
		decomposed.OptimizeImageLayers()
		mw = decomposed.DeconstructImages()
		decomposed.Destroy()
	}

	if format.IsStrip() {
		err := mw.StripImage()
		if err != nil {
			return nil, err
		}
	}

	if quality := format.Quality(); quality > 0 {
		err := mw.SetImageCompressionQuality(quality)
		if err != nil {
			return nil, err
		}

		err = mw.SetCompressionQuality(quality)
		if err != nil {
			return nil, err
		}
	}

	if imageFormat := format.Format(); imageFormat != "" {
		err := mw.SetImageFormat(imageFormat)
		if err != nil {
			return nil, err
		}
	}

	return mw, nil
}

func (s Sampler) cropImage(mw *imagick.MagickWand, crop Crop, format Format) error {
	if crop.IsEmpty() {
		return errMissingCropParameters
	}

	cropWidth := crop.Width
	cropHeight := crop.Height
	cropLeft := crop.Left
	cropTop := crop.Top

	width := int(mw.GetImageWidth())
	height := int(mw.GetImageHeight())

	if cropLeft < 0 || cropLeft >= width {
		return fmt.Errorf("%w: %v", errCropLeftOutOfBounds, cropLeft)
	}

	if cropTop < 0 || cropTop >= height {
		return fmt.Errorf("%w: %v", errCropTopOutOfBounds, cropTop)
	}

	right := cropLeft + cropWidth
	if cropWidth <= 0 || right > width {
		return fmt.Errorf(
			"%w: '%v + %v' ~ '%v x %v'",
			errCropWidthOutOfBounds,
			cropLeft,
			cropWidth,
			width,
			height,
		)
	}

	// try to fix height overflow
	bottom := cropTop + cropHeight
	overflow := bottom - height

	if overflow > 0 && overflow <= 1 {
		cropHeight -= overflow
	}

	bottom = cropTop + cropHeight
	if cropHeight <= 0 || bottom > height {
		return fmt.Errorf(
			"%w: '%v + %v' ~ '%v x %v'",
			errCropHeightOutOfBounds,
			cropTop,
			cropHeight,
			width,
			height,
		)
	}

	fWidth := format.Width()
	fHeight := format.Height()

	if format.IsProportionalCrop() && fWidth > 0 && fHeight > 0 {
		// extend crop to format proportions
		fRatio := float64(fWidth) / float64(fHeight)
		cRatio := float64(cropWidth) / float64(cropHeight)

		if cRatio > fRatio {
			// crop wider than format, need more height
			targetHeight := int(math.Round(float64(cropWidth) / fRatio))
			if targetHeight > height {
				targetHeight = height
			}

			addedHeight := targetHeight - cropHeight

			cropTop -= addedHeight / 2
			if cropTop < 0 {
				cropTop = 0
			}

			cropHeight = targetHeight
		} else {
			// crop higher than format, need more width
			targetWidth := int(math.Round(float64(cropHeight) * fRatio))
			if targetWidth > width {
				targetWidth = width
			}

			addedWidth := targetWidth - cropWidth

			cropLeft -= addedWidth / 2
			if cropLeft < 0 {
				cropLeft = 0
			}

			cropWidth = targetWidth
		}
	}

	return s.crop(mw, cropWidth, cropHeight, cropLeft, cropTop)
}

func (s Sampler) crop(mw *imagick.MagickWand, width int, height int, left int, top int) error {
	err := mw.SetImagePage(0, 0, 0, 0)
	if err != nil {
		return err
	}

	return mw.CropImage(uint(width), uint(height), left, top)
}

func (s Sampler) cropToWidest(mw *imagick.MagickWand, widestRatio float64) error {
	srcWidth := int(mw.GetImageWidth())
	srcHeight := int(mw.GetImageHeight())

	srcRatio := float64(srcWidth) / float64(srcHeight)

	ratioDiff := srcRatio - widestRatio

	if ratioDiff > 0 {
		dstWidth := int(math.Round(widestRatio * float64(srcHeight)))

		return s.crop(mw, dstWidth, srcHeight, (srcWidth-dstWidth)/2, 0)
	}

	return nil
}

func (s Sampler) cropToHighest(mw *imagick.MagickWand, highestRatio float64) error {
	srcWidth := int(mw.GetImageWidth())
	srcHeight := int(mw.GetImageHeight())

	srcRatio := float64(srcWidth) / float64(srcHeight)

	ratioDiff := srcRatio - highestRatio

	if ratioDiff < 0 {
		dstHeight := int(math.Round(float64(srcWidth) / highestRatio))

		return s.crop(mw, srcWidth, dstHeight, 0, (srcHeight-dstHeight)/2)
	}

	return nil
}

func (s Sampler) extendVertical(mw *imagick.MagickWand, format Format) error {
	fRatio := float64(format.Width()) / float64(format.Height())

	srcWidth := int(mw.GetImageWidth())
	srcHeight := int(mw.GetImageHeight())

	topColor := s.extendTopColor(mw)
	if topColor != nil {
		defer topColor.Destroy()
	}

	bottomColor := s.extendBottomColor(mw)
	if bottomColor != nil {
		defer bottomColor.Destroy()
	}

	if topColor != nil || bottomColor != nil {
		targetWidth := srcWidth
		targetHeight := math.Round(float64(targetWidth) / fRatio)

		needHeight := int(math.Round(targetHeight - float64(srcHeight)))
		topHeight := 0
		bottomHeight := 0

		switch {
		case topColor != nil && bottomColor != nil:
			topHeight = needHeight / 2
			bottomHeight = needHeight - topHeight
		case topColor != nil:
			topHeight = needHeight
		case bottomColor != nil:
			bottomHeight = needHeight
		}

		err := mw.ExtentImage(
			uint(targetWidth),
			uint(targetHeight),
			0,
			-topHeight,
		)
		if err != nil {
			return err
		}

		if topColor != nil {
			draw := imagick.NewDrawingWand()
			defer draw.Destroy()

			draw.SetFillColor(topColor)
			draw.SetStrokeColor(topColor)
			draw.Rectangle(
				0,
				0,
				float64(mw.GetImageWidth()),
				float64(topHeight),
			)

			err = mw.DrawImage(draw)
			if err != nil {
				return err
			}
		}

		if bottomColor != nil {
			draw := imagick.NewDrawingWand()
			defer draw.Destroy()

			draw.SetFillColor(bottomColor)
			draw.SetStrokeColor(bottomColor)
			draw.Rectangle(
				0,
				float64(mw.GetImageHeight())-float64(bottomHeight),
				float64(mw.GetImageWidth()),
				float64(mw.GetImageHeight()),
			)

			err = mw.DrawImage(draw)
			if err != nil {
				return err
			}
		}
	}

	return nil
}

func (s Sampler) extendHorizontal(mw *imagick.MagickWand, format Format) error {
	fRatio := float64(format.Width()) / float64(format.Height())

	srcWidth := int(mw.GetImageWidth())
	srcHeight := int(mw.GetImageHeight())

	leftColor := s.extendLeftColor(mw)
	if leftColor != nil {
		defer leftColor.Destroy()
	}

	rightColor := s.extendRightColor(mw)
	if rightColor != nil {
		defer rightColor.Destroy()
	}

	if leftColor != nil || rightColor != nil {
		targetHeight := srcHeight
		targetWidth := int(math.Round(float64(targetHeight) * fRatio))

		needWidth := targetWidth - srcWidth
		leftWidth := 0
		rightWidth := 0

		switch {
		case leftColor != nil && rightColor != nil:
			leftWidth = needWidth / 2
			rightWidth = needWidth - leftWidth
		case leftColor != nil:
			leftWidth = needWidth
		case rightColor != nil:
			rightWidth = needWidth
		}

		err := mw.ExtentImage(
			uint(targetWidth),
			uint(targetHeight),
			-leftWidth,
			0,
		)
		if err != nil {
			return err
		}

		if leftColor != nil {
			draw := imagick.NewDrawingWand()
			defer draw.Destroy()

			draw.SetFillColor(leftColor)
			draw.SetStrokeColor(leftColor)
			draw.Rectangle(
				0,
				0,
				float64(leftWidth),
				float64(mw.GetImageHeight()),
			)

			return mw.DrawImage(draw)
		}

		if rightColor != nil {
			draw := imagick.NewDrawingWand()
			defer draw.Destroy()

			draw.SetFillColor(rightColor)
			draw.SetStrokeColor(rightColor)
			draw.Rectangle(
				float64(int(mw.GetImageWidth())-rightWidth),
				0,
				float64(mw.GetImageWidth()),
				float64(mw.GetImageHeight()),
			)

			return mw.DrawImage(draw)
		}
	}

	return nil
}

func (s Sampler) extendTopColor(mw *imagick.MagickWand) *imagick.PixelWand {
	iterator := mw.NewPixelRegionIterator(0, 0, mw.GetImageWidth(), 1)
	defer iterator.Destroy()

	return s.extendEdgeColor(iterator)
}

func (s Sampler) extendBottomColor(mw *imagick.MagickWand) *imagick.PixelWand {
	iterator := mw.NewPixelRegionIterator(
		0,
		int(mw.GetImageHeight())-1,
		mw.GetImageWidth(),
		1,
	)
	defer iterator.Destroy()

	return s.extendEdgeColor(iterator)
}

func (s Sampler) extendLeftColor(mw *imagick.MagickWand) *imagick.PixelWand {
	iterator := mw.NewPixelRegionIterator(0, 0, 1, mw.GetImageHeight())
	defer iterator.Destroy()

	return s.extendEdgeColor(iterator)
}

func (s Sampler) extendRightColor(mw *imagick.MagickWand) *imagick.PixelWand {
	iterator := mw.NewPixelRegionIterator(
		int(mw.GetImageWidth())-1,
		0,
		1,
		mw.GetImageHeight(),
	)
	defer iterator.Destroy()

	return s.extendEdgeColor(iterator)
}

func (s Sampler) extendEdgeColor(iterator *imagick.PixelIterator) *imagick.PixelWand {
	reds := make([]float64, 0)
	greens := make([]float64, 0)
	blues := make([]float64, 0)

	for {
		row := iterator.GetNextIteratorRow()
		if row == nil {
			break
		}

		for _, pixel := range row {
			reds = append(reds, pixel.GetRed())
			greens = append(greens, pixel.GetGreen())
			blues = append(blues, pixel.GetBlue())
		}
	}

	red := s.standardDeviation(reds)
	green := s.standardDeviation(greens)
	blue := s.standardDeviation(blues)

	limit := 0.01
	if red > limit || green > limit || blue > limit {
		return nil
	}

	color := imagick.NewPixelWand()
	color.SetRed(arraySum(reds) / float64(len(reds)))
	color.SetGreen(arraySum(greens) / float64(len(greens)))
	color.SetBlue(arraySum(blues) / float64(len(blues)))

	return color
}

func (s Sampler) standardDeviation(values []float64) float64 {
	count := len(values)
	if count == 0 {
		return 0.0
	}

	mean := arraySum(values) / float64(count)
	carry := 0.0

	for _, val := range values {
		diff := val - mean
		carry += diff * diff
	}

	return math.Sqrt(carry / float64(count))
}

func arraySum(values []float64) float64 {
	var sum float64
	for _, v := range values {
		sum += v
	}

	return sum
}

func (s Sampler) convertByInnerFit(mw *imagick.MagickWand, format Format) error {
	srcWidth := int(mw.GetImageWidth())
	srcHeight := int(mw.GetImageHeight())
	srcRatio := float64(srcWidth) / float64(srcHeight)

	formatWidth := format.Width()
	formatHeight := format.Height()

	widthLess := formatWidth > 0 && (srcWidth < formatWidth)
	heightLess := formatHeight > 0 && (srcHeight < formatHeight)
	sizeLess := widthLess || heightLess

	ratio := float64(formatWidth) / float64(formatHeight)

	if format.IsReduceOnly() && sizeLess {
		// dont crop
		if !heightLess {
			// resize by height
			scaleHeight := formatHeight
			scaleWidth := int(math.Round(float64(scaleHeight) * srcRatio))

			err := s.scaleImage(mw, scaleWidth, scaleHeight)
			if err != nil {
				return err
			}
		} else if !widthLess {
			// resize by width
			scaleWidth := formatWidth
			scaleHeight := int(math.Round(float64(scaleWidth) / srcRatio))

			err := s.scaleImage(mw, scaleWidth, scaleHeight)
			if err != nil {
				return err
			}
		}
	} else {
		// высчитываем размеры обрезания
		var (
			cropWidth  int
			cropHeight int
			cropLeft   int
			cropTop    int
		)

		if ratio < srcRatio {
			// широкая картинка
			cropWidth = int(math.Round(float64(srcHeight) * ratio))
			cropHeight = srcHeight
			cropLeft = (srcWidth - cropWidth) / 2
			cropTop = 0
		} else {
			// высокая картинка
			cropWidth = srcWidth
			cropHeight = int(math.Round(float64(srcWidth) / ratio))
			cropLeft = 0
			cropTop = (srcHeight - cropHeight) / 2
		}

		err := s.crop(mw, cropWidth, cropHeight, cropLeft, cropTop)
		if err != nil {
			return err
		}

		return s.scaleImage(mw, formatWidth, formatHeight)
	}

	return nil
}

func (s Sampler) convertByOuterFit(mw *imagick.MagickWand, format Format) error {
	srcWidth := int(mw.GetImageWidth())
	srcHeight := int(mw.GetImageHeight())
	srcRatio := float64(srcWidth) / float64(srcHeight)

	formatWidth := format.Width()
	formatHeight := format.Height()

	widthLess := formatWidth > 0 && (srcWidth < formatWidth)
	heightLess := formatHeight > 0 && (srcHeight < formatHeight)
	sizeLess := widthLess || heightLess

	ratio := float64(formatWidth) / float64(formatHeight)

	if format.IsReduceOnly() && sizeLess {
		// dont crop
		if !heightLess {
			// resize by height
			scaleHeight := formatHeight
			scaleWidth := int(math.Round(float64(scaleHeight) * srcRatio))

			err := s.scaleImage(mw, scaleWidth, scaleHeight)
			if err != nil {
				return err
			}
		} else if !widthLess {
			// resize by width
			scaleWidth := formatWidth
			scaleHeight := int(math.Round(float64(scaleWidth) / srcRatio))

			err := s.scaleImage(mw, scaleWidth, scaleHeight)
			if err != nil {
				return err
			}
		}
	} else {
		var (
			scaleWidth  int
			scaleHeight int
		)

		if ratio < srcRatio {
			scaleWidth = formatWidth
			// add top and bottom margins
			scaleHeight = int(math.Round(float64(formatWidth) / srcRatio))
		} else {
			// add left and right margins
			scaleWidth = int(math.Round(float64(formatHeight) * srcRatio))
			scaleHeight = formatHeight
		}

		err := s.scaleImage(mw, scaleWidth, scaleHeight)
		if err != nil {
			return err
		}
	}

	// extend by bg-space
	borderLeft := (formatWidth - int(mw.GetImageWidth())) / 2
	borderTop := (formatHeight - int(mw.GetImageHeight())) / 2

	return mw.ExtentImage(
		uint(formatWidth),
		uint(formatHeight),
		-borderLeft,
		-borderTop,
	)
}

func (s Sampler) convertByMaximumFit(mw *imagick.MagickWand, format Format) error {
	srcWidth := int(mw.GetImageWidth())
	srcHeight := int(mw.GetImageHeight())
	srcRatio := float64(srcWidth) / float64(srcHeight)

	formatWidth := format.Width()
	formatHeight := format.Height()

	widthLess := formatWidth > 0 && (srcWidth < formatWidth)
	heightLess := formatHeight > 0 && (srcHeight < formatHeight)
	sizeLess := widthLess || heightLess

	ratio := float64(formatWidth) / float64(formatHeight)

	if format.IsReduceOnly() && sizeLess {
		if !heightLess {
			// resize by height
			scaleHeight := formatHeight
			scaleWidth := int(math.Round(float64(scaleHeight) * srcRatio))

			return s.scaleImage(mw, scaleWidth, scaleHeight)
		} else if !widthLess {
			// resize by width
			scaleWidth := formatWidth
			scaleHeight := int(math.Round(float64(scaleWidth) / srcRatio))

			return s.scaleImage(mw, scaleWidth, scaleHeight)
		}

		return nil
	}

	var scaleWidth, scaleHeight int

	// высчитываем размеры обрезания
	if ratio < srcRatio {
		scaleWidth = formatWidth
		scaleHeight = int(math.Round(float64(formatWidth) / srcRatio))
	} else {
		// добавляем поля по бокам
		scaleWidth = int(math.Round(float64(formatHeight) * srcRatio))
		scaleHeight = formatHeight
	}

	return s.scaleImage(mw, scaleWidth, scaleHeight)
}

func (s Sampler) convertByWidth(mw *imagick.MagickWand, format Format) error {
	srcWidth := int(mw.GetImageWidth())
	srcRatio := float64(srcWidth) / float64(mw.GetImageHeight())

	widthLess := srcWidth < format.Width()

	scaleWidth := format.Width()
	if format.IsReduceOnly() && widthLess {
		scaleWidth = srcWidth
	}

	scaleHeight := int(math.Round(float64(scaleWidth) / srcRatio))

	return s.scaleImage(mw, scaleWidth, scaleHeight)
}

func (s Sampler) convertByHeight(mw *imagick.MagickWand, format Format) error {
	srcHeight := int(mw.GetImageHeight())
	srcRatio := float64(mw.GetImageWidth()) / float64(srcHeight)

	heightLess := format.Height() > 0 && (srcHeight < format.Height())

	scaleHeight := format.Height()
	if format.IsReduceOnly() && heightLess {
		scaleHeight = srcHeight
	}

	scaleWidth := int(math.Round(float64(scaleHeight) * srcRatio))

	return s.scaleImage(mw, scaleWidth, scaleHeight)
}

func (s Sampler) scaleImage(mw *imagick.MagickWand, width int, height int) error {
	/*if (mw.GetImageFormat() == ImagickFormatGIF) {
		foreach ($imagick as $i) {
			$i->scaleImage($width, $height, false);
		}
	} else {*/
	return mw.ScaleImage(uint(width), uint(height))
}
