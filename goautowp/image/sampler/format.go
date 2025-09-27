package sampler

import (
	"errors"
	"fmt"
	"path/filepath"
	"strings"

	"github.com/autowp/goautowp/config"
)

const (
	PNGExtension  = "png"
	JPEGExtension = "jpeg"
	GIFExtension  = "gif"
	WebPExtension = "webp"
	BMPExtension  = "bmp"
	AVIFExtension = "avif"

	StorageFormatGIF  = "gif"
	StorageFormatPNG  = "png"
	StorageFormatJPEG = "jpeg"
	StorageFormatWebP = "webp"
	StorageFormatAVIF = "avif"
	StorageFormatBMP  = "bmp"

	GoFormatGIF  = "gif"
	GoFormatPNG  = "png"
	GoFormatJPEG = "jpeg"
	GoFormatWebP = "webp"
	GoFormatAVIF = "avif"

	ContentTypeImagePNG  = "image/png"
	ContentTypeImageXPNG = "image/x-png"
	ContentTypeImageJPEG = "image/jpeg"
	ContentTypeImageGIF  = "image/gif"
	ContentTypeImageAVIF = "image/avif"
	ContentTypeImageBMP  = "image/bmp"
	ContentTypeImageWebP = "image/webp"

	ImagickFormatGIF  = "GIF"
	ImagickFormatPNG  = "PNG"
	ImagickFormatJPEG = "JPEG"
	ImagickFormatWebP = "WEBP"
	ImagickFormatAVIF = "AVIF"
)

type ImagickFormat string

var errUnsupportedFormat = errors.New("unsupported format")

type Format struct {
	format             string
	isIgnoreCrop       bool
	width              int
	height             int
	widest             float64
	highest            float64
	isProportionalCrop bool
	background         string
	fitType            config.FitType
	isStrip            bool
	isReduceOnly       bool
	quality            uint
}

var extension2ContentType = map[string]string{
	GIFExtension:  ContentTypeImageGIF,
	PNGExtension:  ContentTypeImagePNG,
	JPEGExtension: ContentTypeImageJPEG,
	WebPExtension: ContentTypeImageWebP,
	AVIFExtension: ContentTypeImageAVIF,
	BMPExtension:  ContentTypeImageBMP,
}

var imagickFormats2ContentType = map[string]string{
	ImagickFormatGIF:  ContentTypeImageGIF,
	ImagickFormatPNG:  ContentTypeImagePNG,
	ImagickFormatJPEG: ContentTypeImageJPEG,
	ImagickFormatWebP: ContentTypeImageWebP,
	ImagickFormatAVIF: ContentTypeImageAVIF,
}

var imagickFormats2Extension = map[string]string{
	ImagickFormatGIF:  GIFExtension,
	ImagickFormatPNG:  PNGExtension,
	ImagickFormatJPEG: JPEGExtension,
	ImagickFormatWebP: WebPExtension,
	ImagickFormatAVIF: AVIFExtension,
}

var formatExt = map[string]string{
	StorageFormatJPEG: JPEGExtension,
	StorageFormatPNG:  PNGExtension,
	StorageFormatGIF:  GIFExtension,
	StorageFormatBMP:  BMPExtension,
	StorageFormatWebP: WebPExtension,
	StorageFormatAVIF: AVIFExtension,
}

var goFormat2Extension = map[string]string{
	GoFormatGIF:  GIFExtension,
	GoFormatJPEG: JPEGExtension,
	GoFormatWebP: WebPExtension,
	GoFormatPNG:  PNGExtension,
	GoFormatAVIF: AVIFExtension,
}

func NewFormat(cfg config.ImageStorageSamplerFormatConfig) *Format {
	return &Format{
		format:             cfg.Format,
		isIgnoreCrop:       cfg.IgnoreCrop,
		width:              cfg.Width,
		height:             cfg.Height,
		isProportionalCrop: cfg.ProportionalCrop,
		background:         cfg.Background,
		fitType:            cfg.FitType,
		isStrip:            cfg.Strip,
		isReduceOnly:       cfg.ReduceOnly,
		widest:             cfg.Widest,
		highest:            cfg.Highest,
		quality:            cfg.Quality,
	}
}

func (f *Format) Quality() uint {
	return f.quality
}

func (f *Format) Format() string {
	return f.format
}

func (f *Format) IsStrip() bool {
	return f.isStrip
}

func (f *Format) Height() int {
	return f.height
}

func (f *Format) Width() int {
	return f.width
}

func (f *Format) FormatExtension() (string, error) {
	if len(f.format) == 0 {
		return "", nil
	}

	value, ok := formatExt[f.format]

	if !ok {
		return "", fmt.Errorf("%w: `%s`", errUnsupportedFormat, f.format)
	}

	return value, nil
}

func (f *Format) IsIgnoreCrop() bool {
	return f.isIgnoreCrop
}

func (f *Format) Widest() float64 {
	return f.widest
}

func (f *Format) Highest() float64 {
	return f.highest
}

func (f *Format) IsProportionalCrop() bool {
	return f.isProportionalCrop
}

func (f *Format) Background() string {
	return f.background
}

func (f *Format) FitType() config.FitType {
	return f.fitType
}

func (f *Format) IsReduceOnly() bool {
	return f.isReduceOnly
}

func ImagickFormatContentType(format string) (string, error) {
	result, ok := imagickFormats2ContentType[format]
	if !ok {
		return "", fmt.Errorf("%w: `%s`", errUnsupportedFormat, format)
	}

	return result, nil
}

func ImagickFormatExtension(format string) (string, error) {
	result, ok := imagickFormats2Extension[format]
	if !ok {
		return "", fmt.Errorf("%w: `%s`", errUnsupportedFormat, format)
	}

	return result, nil
}

func ExtensionContentType(ext string) (string, error) {
	result, ok := extension2ContentType[ext]
	if !ok {
		return "", fmt.Errorf("%w: `%s`", errUnsupportedFormat, ext)
	}

	return result, nil
}

func GoFormat2Extension(ext string) (string, error) {
	result, ok := goFormat2Extension[ext]
	if !ok {
		return "", fmt.Errorf("%w: `%s`", errUnsupportedFormat, ext)
	}

	return result, nil
}

func ContentTypeByFilepath(file string) (string, error) {
	ext := strings.TrimLeft(filepath.Ext(file), ".")

	return ExtensionContentType(ext)
}
