package attrs

import "strconv"

type WheelSize struct {
	Width    int32
	Series   int32
	Radius   float64
	RimWidth float64
}

func (s *WheelSize) TyreName() string {
	if s.Width > 0 || s.Series > 0 || s.Radius > 0 {
		width := "???"
		if s.Width > 0 {
			width = strconv.FormatInt(int64(s.Width), 10)
		}

		series := "??"
		if s.Series > 0 {
			series = strconv.FormatInt(int64(s.Series), 10)
		}

		radius := "??"
		if s.Radius > 0 {
			radius = strconv.FormatFloat(s.Radius, 'f', 1, 64)
		}

		return width + "/" + series + " R" + radius
	}

	return ""
}

func (s *WheelSize) DiskName() string {
	if s.RimWidth > 0 || s.Radius > 0 {
		rimWidth := "?"
		if s.RimWidth > 0 {
			rimWidth = strconv.FormatFloat(s.RimWidth, 'f', 1, 64)
		}

		radius := "??"
		if s.Radius > 0 {
			radius = strconv.FormatFloat(s.Radius, 'f', 1, 64)
		}

		return rimWidth + "J × " + radius
	}

	return ""
}
