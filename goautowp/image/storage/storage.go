package storage

import (
	"bytes"
	"context"
	"database/sql"
	"encoding/json"
	"errors"
	"fmt"
	"image"
	_ "image/gif"  // GIF support
	_ "image/jpeg" // JPEG support
	_ "image/png"  // PNG support
	"io"
	"math"
	"math/rand"
	"net/url"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/image/sampler"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/util"
	"github.com/aws/aws-sdk-go-v2/aws"
	awsconfig "github.com/aws/aws-sdk-go-v2/config"
	"github.com/aws/aws-sdk-go-v2/credentials"
	"github.com/aws/aws-sdk-go-v2/service/s3"
	"github.com/aws/aws-sdk-go-v2/service/s3/types"
	"github.com/doug-martin/goqu/v9"
	_ "github.com/gen2brain/avif" // AVIF support
	"github.com/sirupsen/logrus"
	_ "golang.org/x/image/bmp"  // BMP support
	_ "golang.org/x/image/webp" // WEBP support
	"gopkg.in/gographics/imagick.v3/imagick"
)

const (
	StatusDefault    int = 0
	StatusProcessing int = 1
	StatusFailed     int = 2
)

const (
	maxInsertAttempts         = 15
	maxSameSizeObjectsToFetch = 10
	defaultExtension          = sampler.JPEGExtension
	listBrokenImagesPerPage   = 1000
)

var (
	ErrImageNotFound           = errors.New("image not found")
	errDirNotFound             = errors.New("dir not defined")
	errFormatNotFound          = errors.New("format not found")
	errFailedToFormatImage     = errors.New("failed to format image")
	errFailedToGetImageSize    = errors.New("failed to get image size")
	errInvalidImageID          = errors.New("invalid image id provided")
	errFileSizeDetectionFailed = errors.New("failed to determine file size")
	errParenthesisNotSupported = errors.New("change image name for keys with parenthesis is not supported")
	errNoRowsReturned          = errors.New("no rows returned")
)

type Storage struct {
	config                config.ImageStorageConfig
	db                    *goqu.Database
	dirs                  map[string]*Dir
	formats               map[string]*sampler.Format
	formattedImageDirName string
	sampler               *sampler.Sampler

	// Built once, on first use, and shared from then on - see s3Client.
	s3ClientMutex sync.Mutex
	s3ClientValue *s3.Client
}

type FlushOptions struct {
	Image    int
	Format   string
	Ext      string
	Recreate bool
	Limit    uint
}

func NewStorage(db *goqu.Database, config config.ImageStorageConfig) (*Storage, error) {
	dirs := make(map[string]*Dir)

	for dirName, dirConfig := range config.Dirs {
		dir, err := NewDir(dirConfig.Bucket, dirConfig.NamingStrategy)
		if err != nil {
			return nil, err
		}

		dirs[dirName] = dir
	}

	formats := make(map[string]*sampler.Format)
	for formatName, formatConfig := range config.Formats {
		formats[formatName] = sampler.NewFormat(formatConfig)
	}

	return &Storage{
		config:                config,
		db:                    db,
		dirs:                  dirs,
		formats:               formats,
		formattedImageDirName: "format",
		sampler:               sampler.NewSampler(),
	}, nil
}

func (s *Storage) Image(ctx context.Context, id int) (*Image, error) {
	imgs, err := s.Images(ctx, []int{id})
	if err != nil {
		return nil, err
	}

	if len(imgs) == 0 {
		return nil, ErrImageNotFound
	}

	return imgs[id], nil
}

func (s *Storage) Images(ctx context.Context, ids []int) (map[int]*Image, error) {
	var (
		sts    []schema.ImageRow
		result = make(map[int]*Image, len(ids))
	)

	if len(ids) == 0 {
		return result, nil
	}

	err := s.db.Select(
		schema.ImageTableIDCol,
		schema.ImageTableWidthCol,
		schema.ImageTableHeightCol,
		schema.ImageTableFilesizeCol,
		schema.ImageTableFilepathCol,
		schema.ImageTableDirCol,
		schema.ImageTableCropLeftCol,
		schema.ImageTableCropTopCol,
		schema.ImageTableCropWidthCol,
		schema.ImageTableCropHeightCol,
	).
		From(schema.ImageTable).
		Where(schema.ImageTableIDCol.In(ids)).
		ScanStructsContext(ctx, &sts)
	if err != nil {
		return nil, err
	}

	for _, st := range sts {
		img := Image{
			id:         st.ID,
			width:      st.Width,
			height:     st.Height,
			filepath:   st.Filepath,
			filesize:   st.Filesize,
			dir:        st.Dir,
			cropLeft:   st.CropLeft,
			cropTop:    st.CropTop,
			cropWidth:  st.CropWidth,
			cropHeight: st.CropHeight,
		}

		err = s.populateSrc(ctx, &img)
		if err != nil {
			return nil, err
		}

		result[st.ID] = &img
	}

	return result, nil
}

func (s *Storage) FormattedImage(ctx context.Context, id int, formatName string) (*Image, error) {
	var row schema.ImageRow

	success, err := s.db.Select(
		schema.ImageTableIDCol, schema.ImageTableWidthCol, schema.ImageTableHeightCol, schema.ImageTableFilesizeCol,
		schema.ImageTableFilepathCol, schema.ImageTableDirCol,
	).
		From(schema.ImageTable).
		Join(
			schema.ImageFormattedTable,
			goqu.On(schema.ImageTableIDCol.Eq(schema.ImageFormattedTableImageFormattedIDCol)),
		).
		Where(
			schema.ImageFormattedTableImageIDCol.Eq(id),
			schema.ImageFormattedTableFormatCol.Eq(formatName),
		).ScanStructContext(ctx, &row)
	if err != nil {
		return nil, err
	}

	if success {
		var img Image

		img.id = row.ID
		img.width = row.Width
		img.height = row.Height
		img.filesize = row.Filesize
		img.filepath = row.Filepath
		img.dir = row.Dir

		err = s.populateSrc(ctx, &img)
		if err != nil {
			return nil, err
		}

		return &img, nil
	}

	formattedImageID, err := s.doFormatImage(ctx, id, formatName)
	if err != nil {
		return nil, fmt.Errorf("doFormatImage(): %w", err)
	}

	return s.Image(ctx, formattedImageID)
}

func (s *Storage) ImageBlob(ctx context.Context, imageID int) (io.ReadCloser, error) {
	// find source image
	var iRow schema.ImageRow

	success, err := s.db.Select(
		schema.ImageTableIDCol,
		schema.ImageTableFilepathCol,
		schema.ImageTableDirCol,
	).
		From(schema.ImageTable).
		Where(schema.ImageTableIDCol.Eq(imageID)).
		ScanStructContext(ctx, &iRow)
	if err != nil {
		return nil, err
	}

	if !success {
		return nil, sql.ErrNoRows
	}

	dir := s.dir(iRow.Dir)
	if dir == nil {
		return nil, fmt.Errorf("%w: `%s`", errDirNotFound, iRow.Dir)
	}

	bucket := dir.Bucket()

	s3Client, err := s.s3Client(ctx)
	if err != nil {
		return nil, err
	}

	object, err := s3Client.GetObject(ctx, &s3.GetObjectInput{
		Bucket: &bucket,
		Key:    &iRow.Filepath,
	})
	if err != nil {
		return nil, fmt.Errorf("s3Client.GetObject(%s, %s): %w", bucket, iRow.Filepath, err)
	}

	return object.Body, nil
}

func (s *Storage) Format(name string) *sampler.Format {
	format, ok := s.formats[name]
	if ok {
		return format
	}

	return nil
}

func (s *Storage) AddImageFromImagick(
	ctx context.Context,
	mw *imagick.MagickWand,
	dirName string,
	options GenerateOptions,
) (int, error) {
	var err error

	width := int(mw.GetImageWidth())
	height := int(mw.GetImageHeight())

	if width <= 0 || height <= 0 {
		return 0, fmt.Errorf("%w: (%v x %v)", errFailedToGetImageSize, width, height)
	}

	format := mw.GetImageFormat()

	options.Extension, err = sampler.ImagickFormatExtension(format)
	if err != nil {
		return 0, err
	}

	dir := s.dir(dirName)
	if dir == nil {
		return 0, fmt.Errorf("%w: `%s`", errDirNotFound, dirName)
	}

	blob, err := mw.GetImagesBlob()
	if err != nil {
		return 0, err
	}

	ctx = context.WithoutCancel(ctx)

	id, err := s.generateLockWrite(
		ctx,
		dirName,
		options,
		width,
		height,
		func(fileName string) error {
			s3Client, err := s.s3Client(ctx)
			if err != nil {
				return err
			}

			blobReader := bytes.NewReader(blob)
			bucket := dir.Bucket()

			contentType, err := sampler.ImagickFormatContentType(mw.GetImageFormat())
			if err != nil {
				return err
			}

			_, err = s3Client.PutObject(ctx, &s3.PutObjectInput{
				Key:         &fileName,
				Body:        blobReader,
				Bucket:      &bucket,
				ACL:         types.ObjectCannedACLPublicRead,
				ContentType: &contentType,
			})

			return err
		},
	)
	if err != nil {
		return 0, err
	}

	filesize := len(blob)
	/*exif := s.extractEXIF(id)
	  if exif {
	  	exif = json_encode(exif, JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR)
	  }*/

	_, err = s.db.Update(schema.ImageTable).
		Set(goqu.Record{schema.ImageTableFilesizeColName: filesize}).
		Where(schema.ImageTableIDCol.Eq(id)).
		Executor().ExecContext(ctx)
	if err != nil {
		return 0, err
	}

	return id, nil
}

func (s *Storage) RemoveImage(ctx context.Context, imageID int) error {
	var row schema.ImageRow

	success, err := s.db.Select(schema.ImageTableIDCol, schema.ImageTableDirCol, schema.ImageTableFilepathCol).
		From(schema.ImageTable).
		Where(schema.ImageTableIDCol.Eq(imageID)).
		ScanStructContext(ctx, &row)
	if err != nil {
		return err
	}

	if !success {
		return sql.ErrNoRows
	}

	logrus.Infof("removing image `%s/%s`", row.Dir, row.Filepath)

	ctx = context.WithoutCancel(ctx)

	err = s.Flush(ctx, FlushOptions{
		Image: row.ID,
	})
	if err != nil {
		return err
	}

	// to save remove formatted image
	_, err = s.db.Delete(schema.ImageFormattedTable).
		Where(schema.ImageFormattedTableImageFormattedIDCol.Eq(row.ID)).
		Executor().ExecContext(ctx)
	if err != nil {
		return err
	}

	// important to delete row first
	_, err = s.db.Delete(schema.ImageTable).
		Where(schema.ImageTableIDCol.Eq(row.ID)).
		Executor().
		ExecContext(ctx)
	if err != nil {
		return err
	}

	dir := s.dir(row.Dir)
	if dir == nil {
		return fmt.Errorf("%w: `%s`", errDirNotFound, row.Dir)
	}

	s3Client, err := s.s3Client(ctx)
	if err != nil {
		return err
	}

	bucket := dir.Bucket()
	key := row.Filepath

	_, err = s3Client.DeleteObject(ctx, &s3.DeleteObjectInput{
		Bucket: &bucket,
		Key:    &key,
	})
	if err != nil {
		return err
	}

	return nil
}

func (s *Storage) Flush(ctx context.Context, options FlushOptions) error {
	sqSelect := s.db.Select(schema.ImageFormattedTableImageIDCol, schema.ImageFormattedTableFormatCol,
		schema.ImageFormattedTableImageFormattedIDCol).
		From(schema.ImageFormattedTable)

	if len(options.Format) > 0 {
		sqSelect = sqSelect.Where(schema.ImageFormattedTableFormatCol.Eq(options.Format))
	}

	if options.Image > 0 {
		sqSelect = sqSelect.Where(schema.ImageFormattedTableImageIDCol.Eq(options.Image))
	}

	if len(options.Ext) > 0 {
		sqSelect = sqSelect.
			Join(
				schema.ImageTable,
				goqu.On(schema.ImageFormattedTableImageFormattedIDCol.Eq(schema.ImageTableIDCol)),
			).
			Where(schema.ImageTableFilepathCol.ILike("%." + options.Ext))
	}

	if options.Limit > 0 {
		sqSelect = sqSelect.Limit(options.Limit)
	}

	var rows []schema.ImageFormattedRow

	err := sqSelect.ScanStructsContext(ctx, &rows)
	if err != nil {
		return err
	}

	ctx = context.WithoutCancel(ctx)

	for _, row := range rows {
		logrus.Infof("flushing image `%d/%s`", row.ImageID, row.Format)

		if row.ImageFormattedID.Valid && row.ImageFormattedID.Int32 > 0 {
			err = s.RemoveImage(ctx, int(row.ImageFormattedID.Int32))
			if err != nil {
				return err
			}
		}

		_, err = s.db.Delete(schema.ImageFormattedTable).
			Where(
				schema.ImageFormattedTableImageIDCol.Eq(row.ImageID),
				schema.ImageFormattedTableFormatCol.Eq(row.Format),
			).
			Executor().ExecContext(ctx)
		if err != nil {
			return err
		}

		if options.Recreate {
			_, err = s.FormattedImage(ctx, row.ImageID, row.Format)
			if err != nil {
				return err
			}
		}
	}

	return nil
}

func (s *Storage) ChangeImageName(ctx context.Context, imageID int, options GenerateOptions) error {
	var img schema.ImageRow

	success, err := s.db.Select(schema.ImageTableIDCol, schema.ImageTableDirCol, schema.ImageTableFilepathCol).
		From(schema.ImageTable).
		Where(schema.ImageTableIDCol.Eq(imageID)).
		ScanStructContext(ctx, &img)
	if err != nil {
		return err
	}

	if !success {
		return sql.ErrNoRows
	}

	if strings.Contains(img.Filepath, "(") || strings.Contains(img.Filepath, ")") {
		return errParenthesisNotSupported
	}

	dir := s.dir(img.Dir)
	if dir == nil {
		return fmt.Errorf("%w: `%s`", errDirNotFound, img.Dir)
	}

	if len(options.Extension) == 0 {
		options.Extension = strings.TrimLeft(filepath.Ext(img.Filepath), ".")
	}

	var insertAttemptException error

	s3Client, err := s.s3Client(ctx)
	if err != nil {
		return err
	}

	sourceURL := url.URL{Path: dir.Bucket() + "/" + img.Filepath}
	bucket := dir.Bucket()

	ctx = context.WithoutCancel(ctx)

	for attemptIndex := range maxInsertAttempts {
		options.Index = indexByAttempt(attemptIndex)

		destFileName, err := s.createImagePath(ctx, img.Dir, options)
		if err != nil {
			return err
		}

		if destFileName == img.Filepath {
			// The image already sits at a name that satisfies the pattern - nothing to
			// rename. Treat it as success: a concurrent correction (e.g. triggered by an
			// item rename) may have already moved the file before this call ran, and with
			// the copy&delete implementation a self-rename would copy the object onto
			// itself and then delete it.
			return nil
		}

		destURL := url.URL{Path: destFileName}
		escapedDestURL := destURL.EscapedPath()

		_, insertAttemptException = s.db.Update(schema.ImageTable).
			Set(goqu.Record{schema.ImageTableFilepathColName: destFileName}).
			Where(schema.ImageTableIDCol.Eq(img.ID)).
			Executor().ExecContext(ctx)
		if insertAttemptException == nil {
			_, err = s3Client.CopyObject(ctx, &s3.CopyObjectInput{
				Bucket:     &bucket,
				CopySource: aws.String(sourceURL.EscapedPath()),
				Key:        aws.String(escapedDestURL),
				ACL:        types.ObjectCannedACLPublicRead,
			})
			if err != nil {
				logrus.Errorf(
					"CopyObject from `%s` to `%s` failed: %s",
					sourceURL.EscapedPath(),
					escapedDestURL,
					err.Error(),
				)

				return err
			}

			fpath := img.Filepath

			_, err = s3Client.DeleteObject(ctx, &s3.DeleteObjectInput{
				Bucket: &bucket,
				Key:    &fpath,
			})
			if err != nil {
				return err
			}

			break
		}
	}

	return insertAttemptException
}

func (s *Storage) AddImageFromFilepath(
	ctx context.Context,
	file string,
	dirName string,
	options GenerateOptions,
) (int, error) {
	handle, err := os.Open(file)
	if err != nil {
		return 0, err
	}
	defer util.Close(handle)

	return s.AddImageFromReader(ctx, handle, dirName, options)
}

func (s *Storage) AddImageFromReader(
	ctx context.Context,
	handle io.ReadSeeker,
	dirName string,
	options GenerateOptions,
) (int, error) {
	imageInfo, imageType, err := image.DecodeConfig(handle)
	if err != nil {
		return 0, err
	}

	if imageInfo.Width <= 0 || imageInfo.Height <= 0 {
		return 0, fmt.Errorf(
			"%w: (%v x %v)",
			errFailedToGetImageSize,
			imageInfo.Width,
			imageInfo.Height,
		)
	}

	if len(options.Extension) == 0 {
		options.Extension, err = sampler.GoFormat2Extension(imageType)
		if err != nil {
			return 0, err
		}
	}

	dir := s.dir(dirName)
	if dir == nil {
		return 0, fmt.Errorf("%w: `%s`", errDirNotFound, dirName)
	}

	ctx = context.WithoutCancel(ctx)

	var filesize int64

	id, err := s.generateLockWrite(
		ctx,
		dirName,
		options,
		imageInfo.Width,
		imageInfo.Height,
		func(fileName string) error {
			bucket := dir.Bucket()

			contentType, err := sampler.ExtensionContentType(options.Extension)
			if err != nil {
				return err
			}

			_, err = handle.Seek(0, 0)
			if err != nil {
				return err
			}

			s3Client, err := s.s3Client(ctx)
			if err != nil {
				return err
			}

			_, err = s3Client.PutObject(ctx, &s3.PutObjectInput{
				Key:         &fileName,
				Body:        handle,
				Bucket:      &bucket,
				ACL:         types.ObjectCannedACLPublicRead,
				ContentType: &contentType,
			})
			if err != nil {
				return err
			}

			res, err := s3Client.HeadObject(ctx, &s3.HeadObjectInput{
				Key:    &fileName,
				Bucket: &bucket,
			})
			if err != nil {
				return err
			}

			if res.ContentLength == nil {
				return errFileSizeDetectionFailed
			}

			filesize = *res.ContentLength

			return nil
		},
	)
	if err != nil {
		return 0, err
	}

	/*$exif = $this->extractEXIF($id);
	  if ($exif) {
	  	$exif = json_encode($exif, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
	  }*/

	_, err = s.db.Update(schema.ImageTable).
		Set(goqu.Record{schema.ImageTableFilesizeColName: filesize}).
		Where(schema.ImageTableIDCol.Eq(id)).
		Executor().ExecContext(ctx)
	if err != nil {
		return 0, err
	}

	return id, nil
}

func (s *Storage) AddImageFromBlob(
	ctx context.Context,
	blob []byte,
	dirName string,
	options GenerateOptions,
) (int, error) {
	mw := imagick.NewMagickWand()
	defer mw.Destroy()

	if err := mw.ReadImageBlob(blob); err != nil {
		return 0, err
	}

	id, err := s.AddImageFromImagick(ctx, mw, dirName, options)
	if err != nil {
		return 0, err
	}

	return id, nil
}

func (s *Storage) Flop(ctx context.Context, imageID int) error {
	return s.doImagickOperation(ctx, imageID, func(mw *imagick.MagickWand) error {
		return mw.FlopImage()
	})
}

func (s *Storage) Normalize(ctx context.Context, imageID int) error {
	return s.doImagickOperation(ctx, imageID, func(mw *imagick.MagickWand) error {
		return mw.NormalizeImage()
	})
}

func (s *Storage) SetImageCrop(ctx context.Context, imageID int, crop sampler.Crop) error {
	if imageID <= 0 {
		return fmt.Errorf("%w: `%v`", errInvalidImageID, imageID)
	}

	if crop.Left < 0 || crop.Top < 0 || crop.Width <= 0 || crop.Height <= 0 {
		crop.Left = 0
		crop.Top = 0
		crop.Width = 0
		crop.Height = 0
	} else {
		img, err := s.Image(ctx, imageID)
		if err != nil {
			return err
		}

		crop = sampler.Crop(util.IntersectBounds(util.Rect[int](crop), util.Rect[int]{
			Left:   0,
			Top:    0,
			Width:  img.Width(),
			Height: img.Height(),
		}))

		isFull := crop.Left == 0 && crop.Top == 0 && crop.Width == img.Width() && crop.Height == img.Height()
		if isFull {
			crop.Left = 0
			crop.Top = 0
			crop.Width = 0
			crop.Height = 0
		}
	}

	ctx = context.WithoutCancel(ctx)

	_, err := s.db.Update(schema.ImageTable).
		Set(goqu.Record{
			schema.ImageTableCropLeftColName:   crop.Left,
			schema.ImageTableCropTopColName:    crop.Top,
			schema.ImageTableCropWidthColName:  crop.Width,
			schema.ImageTableCropHeightColName: crop.Height,
		}).
		Where(schema.ImageTableIDCol.Eq(imageID)).
		Executor().ExecContext(ctx)
	if err != nil {
		return err
	}

	for formatName, format := range s.formats {
		if !format.IsIgnoreCrop() {
			err = s.Flush(ctx, FlushOptions{
				Format: formatName,
				Image:  imageID,
			})
			if err != nil {
				return err
			}
		}
	}

	return nil
}

func (s *Storage) ImageCrop(ctx context.Context, imageID int) (*sampler.Crop, error) {
	var crop sampler.Crop

	success, err := s.db.Select(
		schema.ImageTableCropLeftCol,
		schema.ImageTableCropTopCol,
		schema.ImageTableCropWidthCol,
		schema.ImageTableCropHeightCol,
	).
		From(schema.ImageTable).
		Where(
			schema.ImageTableIDCol.Eq(imageID),
			schema.ImageTableCropWidthCol.Gt(0),
			schema.ImageTableCropHeightCol.Gt(0),
		).ScanStructContext(ctx, &crop)
	if err != nil {
		return nil, err
	}

	if !success {
		return nil, sql.ErrNoRows
	}

	return &crop, nil
}

func (s *Storage) FormattedImages(
	ctx context.Context,
	imageIDs []int,
	formatName string,
) (map[int]Image, error) {
	sqSelect := s.db.Select(
		schema.ImageTableIDCol,
		schema.ImageTableWidthCol,
		schema.ImageTableHeightCol,
		schema.ImageTableFilesizeCol,
		schema.ImageTableFilepathCol,
		schema.ImageTableDirCol,
		schema.ImageFormattedTableImageIDCol,
	).
		From(schema.ImageTable).
		Join(
			schema.ImageFormattedTable,
			goqu.On(schema.ImageTableIDCol.Eq(schema.ImageFormattedTableImageFormattedIDCol)),
		).
		Where(
			schema.ImageFormattedTableImageIDCol.In(imageIDs),
			schema.ImageFormattedTableFormatCol.Eq(formatName),
		)

	rows, err := sqSelect.Executor().QueryContext(ctx) //nolint:sqlclosecheck
	if errors.Is(err, sql.ErrNoRows) {
		return make(map[int]Image), nil
	}

	if err != nil {
		return nil, err
	}

	defer util.Close(rows)

	result := make(map[int]Image, len(imageIDs))

	for rows.Next() {
		var (
			img        Image
			srcImageID int
		)

		err = rows.Scan(
			&img.id,
			&img.width,
			&img.height,
			&img.filesize,
			&img.filepath,
			&img.dir,
			&srcImageID,
		)
		if err != nil {
			return nil, err
		}

		err = s.populateSrc(ctx, &img)
		if err != nil {
			return nil, err
		}

		result[srcImageID] = img
	}

	if err = rows.Err(); err != nil {
		return nil, err
	}

	for _, imageID := range imageIDs {
		_, ok := result[imageID]
		if !ok {
			formattedImageID, err := s.doFormatImage(ctx, imageID, formatName)
			if err != nil {
				return nil, err
			}

			img, err := s.Image(ctx, formattedImageID)
			if err != nil {
				return nil, err
			}

			result[imageID] = *img
		}
	}

	return result, nil
}

func (s *Storage) ListBrokenImages(ctx context.Context, dirName string, lastKey string) error {
	dir := s.dir(dirName)
	if dir == nil {
		return fmt.Errorf("%w: `%s`", errDirNotFound, dirName)
	}

	var isLastPage bool

	for !isLastPage {
		fmt.Printf( //nolint:forbidigo
			"Fetch next `%d` from `%s`\n",
			listBrokenImagesPerPage,
			lastKey,
		)

		var sts []struct {
			Filepath string `db:"filepath"`
		}

		err := s.db.Select(schema.ImageTableFilepathCol).
			From(schema.ImageTable).
			Where(
				schema.ImageTableDirCol.Eq(dirName),
				schema.ImageTableFilepathCol.Gt(lastKey),
			).
			Order(schema.ImageTableFilepathCol.Asc()).
			Limit(listBrokenImagesPerPage).
			ScanStructsContext(ctx, &sts)
		if err != nil {
			return err
		}

		isLastPage = len(sts) < listBrokenImagesPerPage

		for _, st := range sts {
			lastKey = st.Filepath

			err = s.isKeyExists(ctx, dir, st.Filepath)
			if err != nil {
				fmt.Println(st.Filepath) //nolint:forbidigo
			}
		}
	}

	return nil
}

func (s *Storage) ListUnlinkedObjects( //nolint: maintidx
	ctx context.Context, dirName string, moveToLostAndFound bool, offset string,
) error {
	dir := s.dir(dirName)
	if dir == nil {
		return fmt.Errorf("%w: `%s`", errDirNotFound, dirName)
	}

	s3Client, err := s.s3Client(ctx)
	if err != nil {
		return err
	}

	bucket := dir.Bucket()

	foundLostImages := make(map[int64][]string)

	var marker *string
	if offset != "" {
		marker = &offset
	}

	paginator := s3.NewListObjectsV2Paginator(s3Client, &s3.ListObjectsV2Input{
		Bucket:            &bucket,
		ContinuationToken: marker,
	})

PAGINATION:
	for paginator.HasMorePages() {
		var id int64

		output, err := paginator.NextPage(ctx)
		if err != nil {
			return err
		}

		for _, item := range output.Contents {
			var itemBytes []byte

			success, err := s.db.Select(schema.ImageTableIDCol).
				From(schema.ImageTable).
				Where(
					schema.ImageTableDirCol.Eq(dirName),
					schema.ImageTableFilepathCol.Eq(*item.Key),
				).
				ScanValContext(ctx, &id)
			if err != nil {
				logrus.Error(err.Error())

				break PAGINATION
			}

			if !success {
				fmt.Printf("\n%s (%v bytes)\n", *item.Key, *item.Size) //nolint:forbidigo

				_, ok := foundLostImages[*item.Size]
				if !ok {
					foundLostImages[*item.Size] = make([]string, 0)
				}

				foundLostImages[*item.Size] = append(foundLostImages[*item.Size], *item.Key)

				var (
					sameSizeKeys     []string
					lostSameSizeKeys = make(map[string]string)
					nonLostSameKeys  []string
				)

				err = s.db.Select(schema.ImageTableFilepathCol).
					From(schema.ImageTable).
					Where(
						schema.ImageTableDirCol.Eq(dirName),
						schema.ImageTableFilesizeCol.Eq(*item.Size),
					).
					Limit(maxSameSizeObjectsToFetch).
					ScanValsContext(ctx, &sameSizeKeys)
				if err != nil {
					logrus.Error(err.Error())

					break PAGINATION
				}

				for _, sameSizeKey := range sameSizeKeys {
					err = s.isKeyExists(ctx, dir, sameSizeKey)
					if err != nil {
						lostSameSizeKeys[sameSizeKey] = err.Error()

						continue
					}

					if itemBytes == nil {
						itemBytes, err = s.getObjectBytes(ctx, bucket, *item.Key)
						if err != nil {
							fmt.Printf( //nolint:forbidigo
								"getObjectBytes(%s, %s): %v\n",
								bucket, *item.Key, err.Error(),
							)

							break PAGINATION
						}
					}

					equal, err := s.isObjectBytesEqual(ctx, bucket, sameSizeKey, itemBytes)
					if err != nil {
						fmt.Printf( //nolint:forbidigo
							"isObjectBytesEqual(%s, %s): %v\n",
							bucket, sameSizeKey, err.Error(),
						)

						break PAGINATION
					}

					if equal {
						nonLostSameKeys = append(nonLostSameKeys, sameSizeKey)
					}
				}

				if len(lostSameSizeKeys) > 0 {
					fmt.Println("Found same size keys lost objects:") //nolint:forbidigo

					for lostSameSizeKey, errMsg := range lostSameSizeKeys {
						fmt.Println(lostSameSizeKey + ": " + errMsg + "\n") //nolint:forbidigo
					}
				} else {
					fmt.Println("No same size keys lost objects found") //nolint:forbidigo

					switch {
					case len(nonLostSameKeys) > 0:
						fmt.Println("But found some equal VALID images:") //nolint:forbidigo

						for _, nonLostSameKey := range nonLostSameKeys {
							fmt.Println("- " + nonLostSameKey) //nolint:forbidigo
						}

						const prefix = "lost-and-has-valid-copy/"
						if moveToLostAndFound && !strings.HasPrefix(*item.Key, prefix) {
							err = s.moveWithPrefix(ctx, bucket, *item.Key, prefix)
							if err != nil {
								fmt.Printf( //nolint:forbidigo
									"moveWithPrefix(%s, %s, %s): %v\n",
									bucket, *item.Key, prefix, err.Error(),
								)

								break PAGINATION
							}
						}

					case len(foundLostImages[*item.Size]) > 1:
						var lostEqual []string

						if itemBytes == nil {
							itemBytes, err = s.getObjectBytes(ctx, bucket, *item.Key)
							if err != nil {
								fmt.Printf( //nolint:forbidigo
									"getObjectBytes(%s, %s): %v\n", bucket, *item.Key, err.Error(),
								)

								break PAGINATION
							}
						}

						for _, key := range foundLostImages[*item.Size] {
							if key != *item.Key {
								equal, err := s.isObjectBytesEqual(ctx, bucket, key, itemBytes)
								if err != nil {
									fmt.Printf( //nolint:forbidigo
										"isObjectBytesEqual(%s, %s): %v\n", bucket, key, err.Error(),
									)

									break PAGINATION
								}

								if equal {
									lostEqual = append(lostEqual, key)
								}
							}
						}

						if len(lostEqual) > 0 {
							fmt.Println("But found some equal LOST images:") //nolint:forbidigo

							for _, key := range lostEqual {
								fmt.Println("- " + key) //nolint:forbidigo
							}
						}
					default:
						const prefix = "lost-and-found/"
						if moveToLostAndFound && !strings.HasPrefix(*item.Key, prefix) {
							err = s.moveWithPrefix(ctx, bucket, *item.Key, prefix)
							if err != nil {
								fmt.Printf( //nolint:forbidigo
									"moveWithPrefix(%s, %s, %s): %v\n",
									bucket, *item.Key, prefix, err.Error(),
								)

								break PAGINATION
							}
						}
					}
				}
			}
		}
	}

	return err
}

func (s *Storage) ImageEXIF(
	ctx context.Context,
	id int,
) (map[string]map[string]interface{}, error) {
	var exifStr sql.NullString

	success, err := s.db.Select(schema.ImageTableEXIFCol).
		From(schema.ImageTable).
		Where(schema.ImageTableIDCol.Eq(id)).
		ScanValContext(ctx, &exifStr)
	if err != nil {
		return nil, err
	}

	if !success {
		return nil, ErrImageNotFound
	}

	if !exifStr.Valid || exifStr.String == "" {
		return nil, nil //nolint: nilnil
	}

	var exif map[string]map[string]interface{}

	err = json.Unmarshal([]byte(exifStr.String), &exif)
	if err != nil {
		logrus.Warnf("failed to unmarshal exif json of `%d`: %s", id, err.Error())

		return nil, nil //nolint: nilnil
	}

	return exif, nil
}

func (s *Storage) Sampler() *sampler.Sampler {
	return s.sampler
}

func (s *Storage) populateSrc(ctx context.Context, img *Image) error {
	dir := s.dir(img.dir)
	if dir == nil {
		return fmt.Errorf("%w: `%s`", errDirNotFound, img.dir)
	}

	bucket := dir.Bucket()

	s3Client, err := s.s3Client(ctx)
	if err != nil {
		return err
	}

	opts := s3Client.Options()

	endpoint, err := s3Client.Options().EndpointResolverV2.ResolveEndpoint(ctx, s3.EndpointParameters{
		Bucket:         &bucket,
		Region:         aws.String(opts.Region),
		ForcePathStyle: aws.Bool(opts.UsePathStyle),
	})
	if err != nil {
		return err
	}

	uri := endpoint.URI

	uri.Path += "/" + img.filepath

	if len(s.config.SrcOverride.Host) > 0 {
		uri.Host = s.config.SrcOverride.Host
	}

	if len(s.config.SrcOverride.Scheme) > 0 {
		uri.Scheme = s.config.SrcOverride.Scheme
	}

	img.src = uri.String()

	return nil
}

func (s *Storage) dir(dirName string) *Dir {
	dir, ok := s.dirs[dirName]
	if ok {
		return dir
	}

	return nil
}

// s3Client returns the shared S3 client, building it on first use.
//
// It used to be built per call, and it is called from a dozen places - several of them once per
// image and per format - so a page of thumbnails loaded the AWS config, parsed the endpoint and
// constructed a credentials cache that many times over. The client is safe for concurrent use, and
// the context only matters while the config is being loaded: requests carry their own.
//
// A failure is not cached, so a build that fails is retried by the next caller rather than
// disabling the storage for the lifetime of the process.
func (s *Storage) s3Client(ctx context.Context) (*s3.Client, error) {
	s.s3ClientMutex.Lock()
	defer s.s3ClientMutex.Unlock()

	if s.s3ClientValue != nil {
		return s.s3ClientValue, nil
	}

	client, err := s.newS3Client(ctx)
	if err != nil {
		return nil, err
	}

	s.s3ClientValue = client

	return client, nil
}

func (s *Storage) newS3Client(ctx context.Context) (*s3.Client, error) {
	cfg, err := awsconfig.LoadDefaultConfig(ctx, awsconfig.WithRegion(s.config.S3.Region))
	if err != nil {
		return nil, err
	}

	endpointURL, err := url.Parse(s.config.S3.Endpoint)
	if err != nil {
		return nil, err
	}

	cfg.RequestChecksumCalculation = aws.RequestChecksumCalculationWhenRequired
	cfg.Credentials = aws.NewCredentialsCache(
		credentials.NewStaticCredentialsProvider(
			s.config.S3.Credentials.Key,
			s.config.S3.Credentials.Secret,
			"",
		),
	)

	return s3.NewFromConfig(cfg, func(o *s3.Options) {
		o.UsePathStyle = s.config.S3.UsePathStyleEndpoint
		o.EndpointResolverV2 = &Resolver{URL: endpointURL}
	}), nil
}

func getCropSuffix(i schema.ImageRow) string {
	result := ""

	if i.CropWidth <= 0 || i.CropHeight <= 0 {
		return result
	}

	return fmt.Sprintf(
		"_%04x%04x%04x%04x",
		i.CropLeft,
		i.CropTop,
		i.CropWidth,
		i.CropHeight,
	)
}

func fileNameWithoutExtension(fileName string) string {
	if pos := strings.LastIndexByte(fileName, '.'); pos != -1 {
		return fileName[:pos]
	}

	return fileName
}

func (s *Storage) doFormatImage( //nolint: maintidx
	ctx context.Context,
	imageID int,
	formatName string,
) (int, error) {
	// find source image
	var iRow schema.ImageRow

	success, err := s.db.Select(
		schema.ImageTableIDCol,
		schema.ImageTableWidthCol,
		schema.ImageTableHeightCol,
		schema.ImageTableFilepathCol,
		schema.ImageTableDirCol,
		schema.ImageTableCropLeftCol,
		schema.ImageTableCropTopCol,
		schema.ImageTableCropWidthCol,
		schema.ImageTableCropHeightCol,
	).
		From(schema.ImageTable).
		Where(schema.ImageTableIDCol.Eq(imageID)).
		ScanStructContext(ctx, &iRow)
	if err != nil {
		return 0, err
	}

	if !success {
		return 0, sql.ErrNoRows
	}

	dir := s.dir(iRow.Dir)
	if dir == nil {
		return 0, fmt.Errorf("%w: `%s`", errDirNotFound, iRow.Dir)
	}

	bucket := dir.Bucket()

	s3Client, err := s.s3Client(ctx)
	if err != nil {
		return 0, err
	}

	object, err := s3Client.GetObject(ctx, &s3.GetObjectInput{
		Bucket: &bucket,
		Key:    &iRow.Filepath,
	})
	if err != nil {
		return 0, fmt.Errorf("s3Client.GetObject(%s, %s): %w", bucket, iRow.Filepath, err)
	}

	mw := imagick.NewMagickWand()
	defer mw.Destroy()

	imgBytes, err := io.ReadAll(object.Body)
	if err != nil {
		return 0, err
	}

	err = mw.ReadImageBlob(imgBytes)
	if err != nil {
		return 0, err
	}

	// format
	format := s.Format(formatName)
	if format == nil {
		return 0, fmt.Errorf("%w: `%s`", errFormatNotFound, formatName)
	}

	ctx = context.WithoutCancel(ctx)

	_, err = s.db.Insert(schema.ImageFormattedTable).Rows(goqu.Record{
		schema.ImageFormattedTableFormatColName:           formatName,
		schema.ImageFormattedTableImageIDColName:          imageID,
		schema.ImageFormattedTableStatusColName:           StatusProcessing,
		schema.ImageFormattedTableImageFormattedIDColName: nil,
	}).Executor().ExecContext(ctx)
	if err != nil {
		if !util.IsPgDuplicateKeyError(err) {
			return 0, err
		}

		// wait until done
		logrus.Debug("Wait until image processing done")

		var (
			done  = false
			fiRow schema.ImageFormattedRow
		)

		for i := 0; i < maxInsertAttempts && !done; i++ {
			success, err = s.db.Select(
				schema.ImageFormattedTableImageFormattedIDCol, schema.ImageFormattedTableStatusCol,
			).
				From(schema.ImageFormattedTable).
				Where(schema.ImageFormattedTableImageIDCol.Eq(imageID)).
				ScanStructContext(ctx, &fiRow)
			if err != nil {
				return 0, err
			}

			if !success {
				return 0, sql.ErrNoRows
			}

			done = fiRow.Status != StatusProcessing
			if !done {
				time.Sleep(time.Second)
			}
		}

		if !done {
			// mark as failed
			_, err = s.db.Update(schema.ImageFormattedTable).
				Set(goqu.Record{schema.ImageFormattedTableStatusColName: StatusFailed}).
				Where(
					schema.ImageFormattedTableFormatCol.Eq(formatName),
					schema.ImageFormattedTableImageIDCol.Eq(imageID),
					schema.ImageFormattedTableStatusCol.Eq(StatusProcessing),
				).
				Executor().ExecContext(ctx)
			if err != nil {
				return 0, err
			}
		}

		if !fiRow.ImageFormattedID.Valid {
			return 0, fmt.Errorf(
				"doFormatImage(%d, %s): %w",
				imageID,
				formatName,
				errFailedToFormatImage,
			)
		}

		return int(fiRow.ImageFormattedID.Int32), nil
	}

	var formattedImageID int
	// try {
	// $crop = $this->getRowCrop(iRow);

	cropSuffix := getCropSuffix(iRow)

	crop := sampler.Crop{
		Left:   int(iRow.CropLeft),
		Top:    int(iRow.CropTop),
		Width:  int(iRow.CropWidth),
		Height: int(iRow.CropHeight),
	}

	mw, err = s.sampler.ConvertImage(mw, crop, *format)
	if err != nil {
		return 0, err
	}

	/*foreach ($cFormat->getProcessors() as $processorName) {
		$processor = $this->processors->get($processorName);
		$processor->process($imagick);
	}*/

	// store result
	newPath := strings.Join([]string{
		iRow.Dir,
		formatName,
		iRow.Filepath,
	}, "/")

	formatExt, err := format.FormatExtension()
	if err != nil {
		return 0, err
	}

	extension := formatExt
	if formatExt == "" {
		extension = strings.TrimLeft(filepath.Ext(newPath), ".")
	}

	formattedImageID, err = s.AddImageFromImagick(
		ctx,
		mw,
		s.formattedImageDirName,
		GenerateOptions{
			Extension: extension,
			Pattern: filepath.Dir(
				newPath,
			) + "/" + fileNameWithoutExtension(
				filepath.Base(newPath),
			) + cropSuffix,
		},
	)
	if err != nil {
		return 0, err
	}

	_, err = s.db.Update(schema.ImageFormattedTable).
		Set(goqu.Record{
			schema.ImageFormattedTableImageFormattedIDColName: formattedImageID,
			schema.ImageFormattedTableStatusColName:           StatusDefault,
		}).
		Where(
			schema.ImageFormattedTableFormatCol.Eq(formatName),
			schema.ImageFormattedTableImageIDCol.Eq(imageID),
		).
		Executor().ExecContext(ctx)
	if err != nil {
		return 0, err
	}

	// } catch (Exception $e) {
	_, err = s.db.Update(schema.ImageFormattedTable).
		Set(goqu.Record{
			schema.ImageFormattedTableStatusColName: StatusFailed,
		}).
		Where(
			schema.ImageFormattedTableFormatCol.Eq(formatName),
			schema.ImageFormattedTableImageIDCol.Eq(imageID),
		).
		Executor().ExecContext(ctx)
	if err != nil {
		return 0, err
	}

	// throw $e;
	// }

	return formattedImageID, nil
}

func (s *Storage) generateLockWrite(
	ctx context.Context,
	dirName string,
	options GenerateOptions,
	width int,
	height int,
	callback func(string) error,
) (int, error) {
	var (
		insertAttemptException error
		imageID                = 0
	)

	for attemptIndex := range maxInsertAttempts {
		insertAttemptException = s.incDirCounter(ctx, dirName)
		if insertAttemptException == nil {
			opt := options
			opt.Index = indexByAttempt(attemptIndex)

			var destFileName string

			destFileName, insertAttemptException = s.createImagePath(ctx, dirName, opt)
			if insertAttemptException == nil {
				var (
					id      int64
					success bool
				)

				// store to db
				success, insertAttemptException = s.db.Insert(schema.ImageTable).Rows(goqu.Record{
					schema.ImageTableWidthColName:      width,
					schema.ImageTableHeightColName:     height,
					schema.ImageTableDirColName:        dirName,
					schema.ImageTableFilesizeColName:   0,
					schema.ImageTableFilepathColName:   destFileName,
					schema.ImageTableDateAddColName:    goqu.Func("NOW"),
					schema.ImageTableCropLeftColName:   0,
					schema.ImageTableCropTopColName:    0,
					schema.ImageTableCropWidthColName:  0,
					schema.ImageTableCropHeightColName: 0,
					schema.ImageTableS3ColName:         true,
				}).
					Returning(schema.ImageTableIDCol).
					Executor().ScanValContext(ctx, &id)
				if insertAttemptException == nil {
					if success {
						insertAttemptException = callback(destFileName)

						imageID = int(id)
					} else {
						insertAttemptException = errNoRowsReturned
					}
				}
			}
		}

		if insertAttemptException == nil {
			break
		}
	}

	return imageID, insertAttemptException
}

func (s *Storage) incDirCounter(ctx context.Context, dirName string) error {
	ctx = context.WithoutCancel(ctx)

	res, err := s.db.Update(schema.ImageDirTable).
		Set(goqu.Record{
			schema.ImageDirTableCountColName: goqu.L("? + 1", schema.ImageDirTableCountCol),
		}).
		Where(schema.ImageDirTableDirCol.Eq(dirName)).
		Executor().ExecContext(ctx)
	if err != nil {
		return err
	}

	affected, err := res.RowsAffected()
	if err != nil {
		return err
	}

	if affected <= 0 {
		_, err = s.db.Insert(schema.ImageDirTable).Rows(goqu.Record{
			schema.ImageDirTableDirColName:   dirName,
			schema.ImageDirTableCountColName: 1,
		}).Executor().ExecContext(ctx)
		if err != nil {
			return err
		}
	}

	return nil
}

func (s *Storage) dirCounter(ctx context.Context, dirName string) (int, error) {
	var result int

	success, err := s.db.Select(schema.ImageDirTableCountCol).
		From(schema.ImageDirTable).
		Where(schema.ImageDirTableDirCol.Eq(dirName)).
		ScanValContext(ctx, &result)
	if err != nil {
		return 0, err
	}

	if !success {
		return 0, sql.ErrNoRows
	}

	return result, nil
}

func indexByAttempt(attempt int) int {
	const powBase = 10

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	float := float64(attempt)
	minVal := int(math.Pow(powBase, float-1))
	maxVal := int(math.Pow(powBase, float) - 1)

	return random.Intn(maxVal-minVal+1) + minVal
}

func (s *Storage) createImagePath(
	ctx context.Context,
	dirName string,
	options GenerateOptions,
) (string, error) {
	dir := s.dir(dirName)
	if dir == nil {
		return "", fmt.Errorf("%w: `%s`", errDirNotFound, dirName)
	}

	namingStrategy := dir.NamingStrategy()

	c, err := s.dirCounter(ctx, dirName)
	if err != nil {
		return "", err
	}

	options.Count = c

	if len(options.Extension) == 0 {
		options.Extension = defaultExtension
	}

	return namingStrategy.Generate(options), nil
}

func (s *Storage) doImagickOperation(
	ctx context.Context,
	imageID int,
	callback func(*imagick.MagickWand) error,
) error {
	var img schema.ImageRow

	success, err := s.db.Select(schema.ImageTableDirCol, schema.ImageTableFilepathCol).
		From(schema.ImageTable).
		Where(schema.ImageTableIDCol.Eq(imageID)).
		ScanStructContext(ctx, &img)
	if err != nil {
		return err
	}

	if !success {
		return sql.ErrNoRows
	}

	dir := s.dir(img.Dir)
	if dir == nil {
		return fmt.Errorf("%w: `%s`", errDirNotFound, img.Dir)
	}

	mw := imagick.NewMagickWand()
	defer mw.Destroy()

	s3Client, err := s.s3Client(ctx)
	if err != nil {
		return err
	}

	bucket := dir.Bucket()
	fpath := img.Filepath

	object, err := s3Client.GetObject(ctx, &s3.GetObjectInput{
		Bucket: &bucket,
		Key:    &fpath,
	})
	if err != nil {
		return err
	}

	imgBytes, err := io.ReadAll(object.Body)
	if err != nil {
		return err
	}

	err = mw.ReadImageBlob(imgBytes)
	if err != nil {
		return err
	}

	err = callback(mw)
	if err != nil {
		return err
	}

	blob, err := mw.GetImagesBlob()
	if err != nil {
		return err
	}

	blobReader := bytes.NewReader(blob)

	contentType, err := sampler.ImagickFormatContentType(mw.GetImageFormat())
	if err != nil {
		return err
	}

	ctx = context.WithoutCancel(ctx)

	_, err = s3Client.PutObject(ctx, &s3.PutObjectInput{
		Key:         &fpath,
		Body:        blobReader,
		Bucket:      &bucket,
		ACL:         types.ObjectCannedACLPublicRead,
		ContentType: &contentType,
	})
	if err != nil {
		return err
	}

	return s.Flush(ctx, FlushOptions{
		Image: imageID,
	})
}

func (s *Storage) images(ctx context.Context, imageIDs []int) (map[int]Image, error) {
	sqSelect := s.db.Select(schema.ImageTableIDCol, schema.ImageTableWidthCol, schema.ImageTableHeightCol,
		schema.ImageTableFilesizeCol, schema.ImageTableFilepathCol, schema.ImageTableDirCol).
		From(schema.ImageTable).
		Where(schema.ImageTableIDCol.In(imageIDs))

	rows, err := sqSelect.Executor().QueryContext(ctx) //nolint:sqlclosecheck
	if errors.Is(err, sql.ErrNoRows) {
		return make(map[int]Image), nil
	}

	if err != nil {
		return nil, err
	}

	defer util.Close(rows)

	result := make(map[int]Image)

	for rows.Next() {
		var img Image

		err = rows.Scan(&img.id, &img.width, &img.height, &img.filesize, &img.filepath, &img.dir)
		if err != nil {
			return nil, err
		}

		err = s.populateSrc(ctx, &img)
		if err != nil {
			return nil, err
		}

		result[img.id] = img
	}

	if err = rows.Err(); err != nil {
		return nil, err
	}

	return result, nil
}

func (s *Storage) isKeyExists(ctx context.Context, dir *Dir, key string) error {
	s3Client, err := s.s3Client(ctx)
	if err != nil {
		return err
	}

	bucket := dir.Bucket()
	_, err = s3Client.HeadObject(ctx, &s3.HeadObjectInput{
		Bucket: &bucket,
		Key:    &key,
	})

	return err
}

func (s *Storage) getObjectBytes(ctx context.Context, bucket string, key string) ([]byte, error) {
	s3Client, err := s.s3Client(ctx)
	if err != nil {
		return nil, err
	}

	object, err := s3Client.GetObject(ctx, &s3.GetObjectInput{
		Bucket: &bucket,
		Key:    &key,
	})
	if err != nil {
		return nil, err
	}

	objectBytes, err := io.ReadAll(object.Body)
	if err != nil {
		return nil, err
	}

	return objectBytes, nil
}

func (s *Storage) isObjectBytesEqual(
	ctx context.Context, bucket string, key string, expectedBytes []byte,
) (bool, error) {
	actualBytes, err := s.getObjectBytes(ctx, bucket, key)
	if err != nil {
		return false, err
	}

	return bytes.Equal(actualBytes, expectedBytes), nil
}

func (s *Storage) moveWithPrefix(
	ctx context.Context,
	bucket string,
	key string,
	prefix string,
) error {
	copySource := bucket + "/" + key
	dest := prefix + key

	ctx = context.WithoutCancel(ctx)

	s3Client, err := s.s3Client(ctx)
	if err != nil {
		return err
	}

	_, err = s3Client.CopyObject(ctx, &s3.CopyObjectInput{
		Bucket:     &bucket,
		CopySource: &copySource,
		Key:        &dest,
		ACL:        types.ObjectCannedACLPublicRead,
	})
	if err != nil {
		return err
	}

	_, err = s3Client.DeleteObject(ctx, &s3.DeleteObjectInput{
		Bucket: &bucket,
		Key:    &key,
	})
	if err != nil {
		return err
	}

	fmt.Printf("was MOVED from `%s` to `%s`\n", copySource, dest) //nolint:forbidigo

	return nil
}
