package sampler

type Crop struct {
	Left   int `db:"crop_left"`
	Top    int `db:"crop_top"`
	Width  int `db:"crop_width"`
	Height int `db:"crop_height"`
}

func (c Crop) IsEmpty() bool {
	return c.Width <= 0 || c.Height <= 0
}
