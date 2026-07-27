package goautowp

import (
	"context"
	"database/sql"
	"encoding/json"
	"errors"
	"image"
	_ "image/gif"  // GIF support
	_ "image/jpeg" // JPEG support
	_ "image/png"  // PNG support
	"io"
	"math/bits"
	"net/http"
	"time"

	"github.com/ajdnik/imghash/v2"
	"github.com/ajdnik/imghash/v2/hashtype"
	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/util"
	"github.com/doug-martin/goqu/v9"
	_ "github.com/gen2brain/avif" // AVIF support
	"github.com/sirupsen/logrus"
	_ "golang.org/x/image/bmp"  // BMP support
	_ "golang.org/x/image/webp" // WEBP support
)

var (
	errInvalidID          = errors.New("invalid id provided")
	errUnexpectedHashType = errors.New("PDQ hasher: unexpected hash type")
)

// threshold is the maximum Hamming distance (out of 256 bits) for two PDQ
// hashes to be considered a likely duplicate. 31 is Meta's published
// recommendation for the PDQ algorithm.
const threshold = 31

// indexDelay is paced between processed AMQP messages so a large backlog
// (e.g. a full-catalogue reindex) doesn't hammer Postgres with back-to-back
// updateDistance scans over the whole df_hash table.
const indexDelay = 100 * time.Millisecond

// fetchImageTimeout bounds how long Index waits on the source image fetch
// (connection, headers, and body read/decode). ListenAMQP's consumer loop
// is single-threaded, so a hung remote host would otherwise stall the whole
// duplicate-finder queue indefinitely.
const fetchImageTimeout = 30 * time.Second

// DuplicateFinder Main Object.
type DuplicateFinder struct {
	db     *goqu.Database
	config config.DuplicateFinderConfig
}

// NewDuplicateFinder constructor.
func NewDuplicateFinder(
	db *goqu.Database,
	config config.DuplicateFinderConfig,
) (*DuplicateFinder, error) {
	s := &DuplicateFinder{
		db:     db,
		config: config,
	}

	return s, nil
}

// ListenAMQP for incoming messages.
func (s *DuplicateFinder) ListenAMQP(ctx context.Context, quitChan chan bool) error {
	rabbitMQ, err := util.ConnectRabbitMQ(s.config.RabbitMQ)
	if err != nil {
		logrus.Error(err)

		return err
	}

	ch, err := rabbitMQ.Channel()
	if err != nil {
		return err
	}
	defer util.Close(ch)

	inQ, err := ch.QueueDeclare(
		s.config.Queue, // name
		false,          // durable
		false,          // delete when unused
		false,          // exclusive
		false,          // no-wait
		nil,            // arguments
	)
	if err != nil {
		return err
	}

	msgs, err := ch.Consume(
		inQ.Name, // queue
		"",       // consumer
		true,     // auto-ack
		false,    // exclusive
		false,    // no-local
		false,    // no-wait
		nil,      // args
	)
	if err != nil {
		return err
	}

	done := false
	for !done {
		select {
		case <-quitChan:
			logrus.Info("DuplicateFinder got quit signal")

			done = true

			break
		case msg := <-msgs:
			if msg.ContentType != "application/json" {
				logrus.Errorf("unexpected mime `%v`", msg.ContentType)

				continue
			}

			var message pictures.DuplicateFinderInputMessage

			err := json.Unmarshal(msg.Body, &message)
			if err != nil {
				logrus.Errorf("failed to parse json `%s`: %s", err.Error(), msg.Body)

				continue
			}

			err = s.Index(ctx, message.PictureID, message.URL)
			if err != nil {
				logrus.Errorf("error indexing image `%d`/`%s`: %v", message.PictureID, message.URL, err)
			}

			time.Sleep(indexDelay)
		}
	}

	logrus.Info("Disconnecting RabbitMQ")

	return rabbitMQ.Close()
}

// Index picture image
// #nosec G107
func (s *DuplicateFinder) Index(ctx context.Context, id int64, url string) error {
	logrus.Infof("Indexing picture %v", id)

	fetchCtx, cancel := context.WithTimeout(ctx, fetchImageTimeout)
	defer cancel()

	req, err := http.NewRequestWithContext(fetchCtx, http.MethodGet, url, nil)
	if err != nil {
		return err
	}

	resp, err := http.DefaultClient.Do(req) //nolint:bodyclose
	if err != nil {
		return err
	}
	defer util.Close(resp.Body)

	logrus.Infof("Calculate hash for %v", url)

	hash, err := getFileHash(resp.Body)
	if err != nil {
		return err
	}

	ctx = context.WithoutCancel(ctx)

	_, err = s.db.Insert(schema.DfHashTable).Rows(goqu.Record{
		schema.DfHashTablePictureIDColName: id,
		schema.DfHashTableHashColName:      bitString(hash),
	}).Executor().ExecContext(ctx)
	if err != nil {
		return err
	}

	return s.updateDistance(ctx, id)
}

func (s *DuplicateFinder) HideSimilar(ctx context.Context, srcPictureID, dstPictureID int64) error {
	_, err := s.db.Update(schema.DfDistanceTable).
		Set(goqu.Record{
			schema.DfDistanceTableHideColName: true,
		}).
		Where(
			goqu.Or(
				goqu.And(
					schema.DfDistanceTableSrcPictureIDCol.Eq(srcPictureID),
					schema.DfDistanceTableDstPictureIDCol.Eq(dstPictureID),
				),
				goqu.And(
					schema.DfDistanceTableSrcPictureIDCol.Eq(dstPictureID),
					schema.DfDistanceTableDstPictureIDCol.Eq(srcPictureID),
				),
			),
		).
		Executor().ExecContext(ctx)

	return err
}

// getFileHash computes a 256-bit PDQ perceptual hash of the image.
// PDQ (Meta's "Perceptual DCT Quality" hash) is far more resistant to
// rescaling, recompression, and minor edits than a plain 64-bit pHash,
// which makes it a better fit for catalogue duplicate detection.
func getFileHash(reader io.Reader) ([]byte, error) {
	img, _, err := image.Decode(reader)
	if err != nil {
		return nil, err
	}

	hasher, err := imghash.NewPDQ()
	if err != nil {
		return nil, err
	}

	hash, err := hasher.Calculate(img)
	if err != nil {
		return nil, err
	}

	bin, ok := hash.(hashtype.Binary)
	if !ok {
		return nil, errUnexpectedHashType
	}

	return []byte(bin), nil
}

// hammingDistance returns the number of differing bits between two
// equal-length PDQ hashes. Mirrors, in Go, the distance the SQL in
// updateDistance computes via bit_count(a.hash # b.hash) — kept as a pure
// function so hash quality can be unit tested without a database.
func hammingDistance(a, b []byte) int {
	distance := 0
	for i := range a {
		distance += bits.OnesCount8(a[i] ^ b[i])
	}

	return distance
}

// bitString renders a 32-byte PDQ hash as Postgres bit-string text (256
// '0'/'1' characters) for storage in the df_hash.hash bit(256) column.
// Postgres has no cast from bytea to bit/bit varying, so the hash can't be
// passed through as a []byte parameter.
func bitString(hash []byte) string {
	out := make([]byte, 0, len(hash)*8) //nolint:mnd
	for _, b := range hash {
		for i := 7; i >= 0; i-- {
			bit := byte('0')
			if b&(1<<uint(i)) != 0 {
				bit = '1'
			}

			out = append(out, bit)
		}
	}

	return string(out)
}

func (s *DuplicateFinder) updateDistance(ctx context.Context, id int64) error {
	if id <= 0 {
		return errInvalidID
	}

	var exists bool

	found, err := s.db.Select(goqu.V(1)).
		From(schema.DfHashTable).
		Where(schema.DfHashTablePictureIDCol.Eq(id)).
		ScanValContext(ctx, &exists)
	if err != nil {
		return err
	}

	if !found {
		return sql.ErrNoRows
	}

	const (
		ownAlias      = "own"
		otherAlias    = "other"
		distanceAlias = "distance"
	)

	own := goqu.T(ownAlias)
	other := goqu.T(otherAlias)

	var sts []struct {
		PictureID int64 `db:"picture_id"`
		Distance  int   `db:"distance"`
	}

	err = s.db.Select(goqu.Star()).
		From(s.db.Select(
			other.Col(schema.DfHashTablePictureIDColName),
			goqu.Func("BIT_COUNT", goqu.L(
				"? # ?",
				own.Col(schema.DfHashTableHashColName),
				other.Col(schema.DfHashTableHashColName),
			)).As(distanceAlias),
		).
			From(
				schema.DfHashTable.As(ownAlias),
				schema.DfHashTable.As(otherAlias),
			).
			Where(
				own.Col(schema.DfHashTablePictureIDColName).Eq(id),
				other.Col(schema.DfHashTablePictureIDColName).Neq(id),
			)).
		Where(goqu.C(distanceAlias).Lte(threshold)).Executor().ScanStructsContext(ctx, &sts)
	if err != nil {
		return err
	}

	if len(sts) == 0 {
		return nil
	}

	records := make([]goqu.Record, 0, len(sts)*2)

	for _, st := range sts {
		records = append(records, goqu.Record{
			schema.DfDistanceTableSrcPictureIDColName: id,
			schema.DfDistanceTableDstPictureIDColName: st.PictureID,
			schema.DfDistanceTableDistanceColName:     st.Distance,
			schema.DfDistanceTableHideColName:         false,
		}, goqu.Record{
			schema.DfDistanceTableSrcPictureIDColName: st.PictureID,
			schema.DfDistanceTableDstPictureIDColName: id,
			schema.DfDistanceTableDistanceColName:     st.Distance,
			schema.DfDistanceTableHideColName:         false,
		})
	}

	_, err = s.db.Insert(schema.DfDistanceTable).
		Rows(records).
		OnConflict(goqu.DoUpdate(
			schema.DfDistanceTableSrcPictureIDColName+","+schema.DfDistanceTableDstPictureIDColName,
			goqu.Record{
				schema.DfDistanceTableDistanceColName: schema.Excluded(schema.DfDistanceTableDistanceColName),
			},
		)).
		Executor().ExecContext(ctx)

	return err
}
