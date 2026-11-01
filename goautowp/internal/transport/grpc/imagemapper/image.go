package imagemapper

import (
	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/image/storage"
)

func FromStorage(image *storage.Image) *pb.Image {
	if image == nil {
		return nil
	}

	return &pb.Image{ //nolint:exhaustruct
		Id:         int32(image.ID()), //nolint: gosec
		Src:        image.Src(),
		Width:      int32(image.Width()),    //nolint: gosec
		Height:     int32(image.Height()),   //nolint: gosec
		Filesize:   int32(image.FileSize()), //nolint: gosec
		CropLeft:   int32(image.CropLeft()),
		CropTop:    int32(image.CropTop()),
		CropWidth:  int32(image.CropWidth()),
		CropHeight: int32(image.CropHeight()),
	}
}
