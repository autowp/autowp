package goautowp

import (
	"context"
	"errors"
	"slices"
	"sync/atomic"
	"testing"
	"time"

	"github.com/autowp/goautowp/image/storage"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/users"
	"github.com/stretchr/testify/require"
)

var errUserBatchTest = errors.New("user batch test error")

type fakeBatchUserImages struct {
	beforeBatch func(context.Context, []int, string) error
	batches     map[string][][]int
	imageCalls  int
	missing     map[int]bool
	err         error
}

func (s *fakeBatchUserImages) Image(_ context.Context, id int) (*storage.Image, error) {
	s.imageCalls++
	if s.missing[id] {
		return nil, storage.ErrImageNotFound
	}

	return &storage.Image{}, s.err
}

func (s *fakeBatchUserImages) FormattedImages(
	ctx context.Context,
	ids []int,
	format string,
) (map[int]storage.Image, error) {
	if s.batches == nil {
		s.batches = make(map[string][][]int)
	}

	s.batches[format] = append(s.batches[format], slices.Clone(ids))
	if s.beforeBatch != nil {
		if err := s.beforeBatch(ctx, ids, format); err != nil {
			return nil, err
		}
	}

	if s.err != nil {
		return nil, s.err
	}

	images := make(map[int]storage.Image, len(ids))
	for _, id := range ids {
		if s.missing[id] {
			return nil, storage.ErrImageNotFound
		}

		images[id] = storage.Image{}
	}

	return images, nil
}

type fakeUserPictureCounts struct {
	beforeCount func(context.Context, []int64) error
	ids         []int64
	calls       int
	counts      map[int64]int32
	err         error
}

func (s *fakeUserPictureCounts) AcceptedCountsByOwnerIDs(ctx context.Context, ids []int64) (map[int64]int32, error) {
	s.ids = slices.Clone(ids)
	s.calls++

	if s.beforeCount != nil {
		if err := s.beforeCount(ctx, ids); err != nil {
			return nil, err
		}
	}

	return s.counts, s.err
}

func TestUserExtractorBatchesImagesAndCounts(t *testing.T) {
	t.Parallel()

	imageOne, imageTwo, hiddenImage := 1, 2, 4
	email := "private@example.com"
	images := &fakeBatchUserImages{}
	counts := &fakeUserPictureCounts{counts: map[int64]int32{1: 10, 3: 30}}
	extractor := &UserExtractor{imageStorage: images, picturesRepository: counts}
	rows := []schema.UsersRow{
		{ID: 3, Name: "Third", Img: &imageOne, EMail: &email},
		{ID: 1, Name: "Self", Img: &imageOne, EMail: &email},
		{ID: 2, Name: "Second", Img: &imageTwo},
		{ID: 4, Name: "Deleted", Deleted: true, Img: &hiddenImage},
	}
	result, err := extractor.ExtractRows(t.Context(), rows, &UserFields{
		Photo: true, Img: true, PicturesAcceptedCount: true, Email: true,
	}, 1, nil)
	require.NoError(t, err)
	require.Len(t, result, 4)
	require.Equal(t, []int64{3, 1, 2, 4}, []int64{
		result[0].GetId(), result[1].GetId(), result[2].GetId(), result[3].GetId(),
	})
	require.Equal(t, [][]int{{1, 2}}, images.batches["avatar"])
	require.Equal(t, [][]int{{1, 2}}, images.batches["photo"])
	require.Equal(t, 1, images.imageCalls)
	require.Equal(t, 1, counts.calls)
	require.Equal(t, []int64{1, 2, 3}, counts.ids)
	require.EqualValues(t, 30, result[0].GetPicturesAcceptedCount())
	require.EqualValues(t, 10, result[1].GetPicturesAcceptedCount())
	require.Zero(t, result[2].GetPicturesAcceptedCount())
	require.Empty(t, result[0].GetEmail())
	require.Equal(t, email, result[1].GetEmail())
	require.NotNil(t, result[0].GetAvatar())
	require.NotNil(t, result[0].GetPhoto())
	require.Nil(t, result[3].GetAvatar())
	require.Empty(t, result[3].GetName())
}

func TestUserExtractorSkipsUnrequestedBatches(t *testing.T) {
	t.Parallel()

	imageID := 1
	images := &fakeBatchUserImages{}
	counts := &fakeUserPictureCounts{}
	extractor := &UserExtractor{imageStorage: images, picturesRepository: counts}
	result, err := extractor.ExtractRows(t.Context(), []schema.UsersRow{{ID: 1, Img: &imageID}}, nil, 0, nil)
	require.NoError(t, err)
	require.Len(t, result, 1)
	require.Len(t, images.batches, 1)
	require.Zero(t, images.imageCalls)
	require.Zero(t, counts.calls)

	empty, err := (&UserExtractor{}).ExtractRows(t.Context(), nil, &UserFields{Photo: true}, 0, nil)
	require.NoError(t, err)
	require.Empty(t, empty)
}

func TestUserExtractorMissingAvatarDoesNotFailBatch(t *testing.T) {
	t.Parallel()

	imageID, missingID := 1, 999
	images := &fakeBatchUserImages{missing: map[int]bool{missingID: true}}
	extractor := &UserExtractor{imageStorage: images}
	result, err := extractor.ExtractRows(t.Context(), []schema.UsersRow{
		{ID: 1, Img: &imageID}, {ID: 2, Img: &missingID},
	}, &UserFields{Photo: true}, 0, nil)
	require.NoError(t, err)
	require.Len(t, result, 2)
	require.NotNil(t, result[0].GetAvatar())
	require.NotNil(t, result[0].GetPhoto())
	require.Nil(t, result[1].GetAvatar())
	require.Nil(t, result[1].GetPhoto())
	require.Len(t, images.batches["avatar"], 3)
	require.Equal(t, [][]int{{imageID}}, images.batches["photo"])
}

func TestUserExtractorIncludesDeletedDataForAdmin(t *testing.T) {
	t.Parallel()

	imageID := 1
	images := &fakeBatchUserImages{}
	extractor := &UserExtractor{imageStorage: images}
	result, err := extractor.ExtractRows(t.Context(), []schema.UsersRow{
		{ID: 1, Name: "Deleted", Deleted: true, Img: &imageID},
	}, nil, 0, []string{users.RoleAdmin})
	require.NoError(t, err)
	require.Equal(t, "Deleted", result[0].GetName())
	require.NotNil(t, result[0].GetAvatar())
}

func TestUserExtractorPropagatesBatchErrors(t *testing.T) {
	t.Parallel()

	imageID := 1
	extractor := &UserExtractor{imageStorage: &fakeBatchUserImages{err: errUserBatchTest}}
	_, err := extractor.ExtractRows(t.Context(), []schema.UsersRow{{ID: 1, Img: &imageID}}, nil, 0, nil)
	require.ErrorIs(t, err, errUserBatchTest)
	extractor = &UserExtractor{picturesRepository: &fakeUserPictureCounts{err: errUserBatchTest}}
	_, err = extractor.ExtractRows(t.Context(), []schema.UsersRow{{ID: 1}}, &UserFields{
		PicturesAcceptedCount: true,
	}, 0, nil)
	require.ErrorIs(t, err, errUserBatchTest)
}

func TestUserExtractorLoadsImagesAndCountsConcurrently(t *testing.T) {
	t.Parallel()

	ctx, cancel := context.WithTimeout(t.Context(), 5*time.Second)
	defer cancel()

	avatarStarted := make(chan struct{})
	countsStarted := make(chan struct{})
	avatarFinished := make(chan struct{})
	images := &fakeBatchUserImages{beforeBatch: func(ctx context.Context, _ []int, format string) error {
		if format == "photo" {
			select {
			case <-avatarFinished:
				return nil
			default:
				return errUserBatchTest
			}
		}

		close(avatarStarted)

		select {
		case <-countsStarted:
			close(avatarFinished)

			return nil
		case <-ctx.Done():
			return ctx.Err()
		}
	}}
	counts := &fakeUserPictureCounts{
		counts: map[int64]int32{1: 10},
		beforeCount: func(ctx context.Context, _ []int64) error {
			close(countsStarted)

			select {
			case <-avatarStarted:
				return nil
			case <-ctx.Done():
				return ctx.Err()
			}
		},
	}
	extractor := &UserExtractor{imageStorage: images, picturesRepository: counts}
	imageID := 7
	result, err := extractor.ExtractRows(ctx, []schema.UsersRow{{ID: 1, Img: &imageID}},
		&UserFields{Photo: true, PicturesAcceptedCount: true}, 0, nil)
	require.NoError(t, err)
	require.NoError(t, ctx.Err())
	require.Len(t, result, 1)
	require.NotNil(t, result[0].GetAvatar())
	require.NotNil(t, result[0].GetPhoto())
	require.EqualValues(t, 10, result[0].GetPicturesAcceptedCount())
	require.Equal(t, [][]int{{imageID}}, images.batches["avatar"])
	require.Equal(t, [][]int{{imageID}}, images.batches["photo"])
	require.Equal(t, []int64{1}, counts.ids)
}

func TestUserExtractorCancelsFailedBatchSiblings(t *testing.T) {
	t.Parallel()

	for _, testCase := range []struct {
		name       string
		failImages bool
	}{
		{name: "images fail", failImages: true},
		{name: "counts fail"},
	} {
		t.Run(testCase.name, func(t *testing.T) {
			t.Parallel()

			ctx, cancel := context.WithTimeout(t.Context(), 5*time.Second)
			defer cancel()

			started := make(chan struct{})

			var joined atomic.Bool

			blocking := func(ctx context.Context) error {
				close(started)
				<-ctx.Done()
				joined.Store(true)

				return ctx.Err()
			}
			failing := func(ctx context.Context) error {
				select {
				case <-started:
					return errUserBatchTest
				case <-ctx.Done():
					return ctx.Err()
				}
			}

			imageLookup, countLookup := blocking, failing
			if testCase.failImages {
				imageLookup, countLookup = failing, blocking
			}

			images := &fakeBatchUserImages{beforeBatch: func(ctx context.Context, _ []int, _ string) error {
				return imageLookup(ctx)
			}}
			counts := &fakeUserPictureCounts{beforeCount: func(ctx context.Context, _ []int64) error {
				return countLookup(ctx)
			}}
			extractor := &UserExtractor{imageStorage: images, picturesRepository: counts}
			imageID := 7
			result, err := extractor.ExtractRows(ctx, []schema.UsersRow{{ID: 1, Img: &imageID}},
				&UserFields{Photo: true, PicturesAcceptedCount: true}, 0, nil)
			require.ErrorIs(t, err, errUserBatchTest)
			require.NoError(t, ctx.Err(),
				"sibling failure must cancel promptly without waiting for the parent deadline")
			require.Nil(t, result)
			require.True(t, joined.Load(), "all started batches must finish before returning")
			require.Empty(t, images.batches["photo"], "failed avatar loads must not start photo loads")
		})
	}
}
