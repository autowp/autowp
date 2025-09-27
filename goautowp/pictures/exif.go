package pictures

import (
	"errors"
	"io"
	"strings"
	"time"

	"github.com/autowp/goautowp/image/sampler"
	"github.com/dsoprea/go-exif/v3"
	exifcommon "github.com/dsoprea/go-exif/v3/common"
	heicexif "github.com/dsoprea/go-heic-exif-extractor/v2"
	jpegstructure "github.com/dsoprea/go-jpeg-image-structure/v2"
	pngstructure "github.com/dsoprea/go-png-image-structure/v2"
	riimage "github.com/dsoprea/go-utility/v2/image"
)

const (
	EXIFDateTimeOriginal  = 0x9003
	EXIFCopyright         = 0x8298
	EXIFDateTimeDigitized = 0x9004
)

type exifExtractedValues struct {
	copyrights   string
	dateTimeTake time.Time
	gpsInfo      *exif.GpsInfo
}

func extractFromEXIF(
	imageType string,
	handle io.ReadSeeker,
	size int64,
) (exifExtractedValues, error) {
	var parser riimage.MediaParser

	switch imageType {
	case sampler.GoFormatJPEG:
		parser = jpegstructure.NewJpegMediaParser()
	case sampler.GoFormatPNG:
		parser = pngstructure.NewPngMediaParser()
	case sampler.GoFormatAVIF:
		parser = heicexif.NewHeicExifMediaParser()
	}

	if parser == nil {
		return exifExtractedValues{}, nil
	}

	mc, err := parser.Parse(handle, int(size))
	if err != nil {
		return exifExtractedValues{}, err
	}

	rootIfd, _, err := mc.Exif()
	if err != nil {
		if errors.Is(err, exif.ErrNoExif) {
			return exifExtractedValues{}, nil
		}

		return exifExtractedValues{}, err
	}

	copyrightsIfd, err := rootIfd.FindTagWithId(EXIFCopyright)
	if err != nil && !errors.Is(err, exif.ErrTagNotFound) {
		return exifExtractedValues{}, err
	}

	copyrights := make([]string, 0)

	if err == nil {
		for _, line := range copyrightsIfd {
			phrase, err := line.FormatFirst()
			if err != nil {
				return exifExtractedValues{}, err
			}

			copyrights = append(copyrights, phrase)
		}
	}

	exifStdIfd, err := rootIfd.ChildWithIfdPath(exifcommon.IfdExifStandardIfdIdentity)
	if err != nil && !errors.Is(err, exif.ErrTagNotFound) {
		return exifExtractedValues{}, err
	}

	var dateTimeTake time.Time

	if err == nil {
		for _, tagID := range []uint16{EXIFDateTimeOriginal, EXIFDateTimeDigitized} {
			tag, err := exifStdIfd.FindTagWithId(tagID)
			if err != nil && !errors.Is(err, exif.ErrTagNotFound) {
				return exifExtractedValues{}, err
			}

			if err == nil {
				for _, entry := range tag {
					phrase, err := entry.FormatFirst()
					if err != nil {
						return exifExtractedValues{}, err
					}

					dateTimeTake, err = time.Parse("2006:01:02 15:04:05", phrase)
					if err != nil {
						dateTimeTake, err = time.Parse("2006:01:02 15:04:05Z", phrase)
					}

					if err != nil {
						dateTimeTake = time.Time{}
					}
				}
			}

			if !dateTimeTake.IsZero() {
				break
			}
		}
	}

	gpsIfd, err := rootIfd.ChildWithIfdPath(exifcommon.IfdGpsInfoStandardIfdIdentity)
	if err != nil && !errors.Is(err, exif.ErrTagNotFound) {
		return exifExtractedValues{}, err
	}

	var gi *exif.GpsInfo
	if err == nil {
		gi, err = gpsIfd.GpsInfo()
		if err != nil && !errors.Is(err, exif.ErrNoGpsTags) {
			return exifExtractedValues{}, err
		}

		if err != nil {
			gi = nil
		}
	}

	return exifExtractedValues{
		copyrights:   strings.Join(copyrights, "\n"),
		dateTimeTake: dateTimeTake,
		gpsInfo:      gi,
	}, nil
}
