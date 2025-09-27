package pictures

type PictureItemArea struct {
	Left   uint16
	Top    uint16
	Width  uint16
	Height uint16
}

func (c PictureItemArea) IsEmpty() bool {
	return c.Width <= 0 || c.Height <= 0
}
