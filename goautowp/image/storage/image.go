package storage

type Image struct {
	id         int
	width      int
	height     int
	filepath   string
	filesize   int
	src        string
	dir        string
	cropLeft   uint16
	cropTop    uint16
	cropWidth  uint16
	cropHeight uint16
}

func (s Image) ID() int {
	return s.id
}

func (s Image) Src() string {
	return s.src
}

func (s Image) Width() int {
	return s.width
}

func (s Image) Height() int {
	return s.height
}

func (s Image) FileSize() int {
	return s.filesize
}

func (s Image) Dir() string {
	return s.dir
}

func (s Image) Filepath() string {
	return s.filepath
}

func (s Image) CropLeft() uint16 {
	return s.cropLeft
}

func (s Image) CropTop() uint16 {
	return s.cropTop
}

func (s Image) CropWidth() uint16 {
	return s.cropWidth
}

func (s Image) CropHeight() uint16 {
	return s.cropHeight
}
