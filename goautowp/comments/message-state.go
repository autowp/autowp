package comments

import (
	"context"
	"database/sql"
	"maps"
	"slices"

	"github.com/autowp/goautowp/schema"
)

// UserVotes reads only the requested user's votes for a page of messages.
func (s *Repository) UserVotes(ctx context.Context, userID int64, messageIDs []int64) (map[int64]int32, error) {
	result := make(map[int64]int32)
	if len(messageIDs) == 0 {
		return result, nil
	}

	var rows []struct {
		CommentID int64 `db:"comment_id"`
		Vote      int32 `db:"vote"`
	}

	err := s.db.From(schema.CommentVoteTable).
		Select(schema.CommentVoteTableCommentIDCol, schema.CommentVoteTableVoteCol).
		Where(schema.CommentVoteTableUserIDCol.Eq(userID), schema.CommentVoteTableCommentIDCol.In(messageIDs)).
		ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}

	for _, row := range rows {
		result[row.CommentID] = row.Vote
	}

	return result, nil
}

// NewMessages retains the inclusive timestamp comparison used by IsNewMessage.
func (s *Repository) NewMessages(
	ctx context.Context, userID int64, messages []*schema.CommentMessageRow,
) (map[int64]bool, error) {
	result := make(map[int64]bool, len(messages))
	if len(messages) == 0 {
		return result, nil
	}

	types := make(map[schema.CommentMessageType]struct{})
	items := make(map[int64]struct{})

	for _, message := range messages {
		types[message.TypeID] = struct{}{}
		items[message.ItemID] = struct{}{}
	}

	var rows []struct {
		TypeID    schema.CommentMessageType `db:"type_id"`
		ItemID    int64                     `db:"item_id"`
		Timestamp sql.NullTime              `db:"timestamp"`
	}

	err := s.db.From(schema.CommentTopicViewTable).
		Select(schema.CommentTopicViewTableTypeIDCol, schema.CommentTopicViewTableItemIDCol,
			schema.CommentTopicViewTableTimestampCol).
		Where(
			schema.CommentTopicViewTableUserIDCol.Eq(userID),
			schema.CommentTopicViewTableTypeIDCol.In(slices.Collect(maps.Keys(types))),
			schema.CommentTopicViewTableItemIDCol.In(slices.Collect(maps.Keys(items)))).
		ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}
	// Keep the latest timestamp in case a topic has more than one view row.
	latest := make(map[[2]int64]sql.NullTime, len(rows))
	for _, row := range rows {
		key := [2]int64{int64(row.TypeID), row.ItemID}

		previous := latest[key]
		if row.Timestamp.Valid && (!previous.Valid || row.Timestamp.Time.After(previous.Time)) {
			latest[key] = row.Timestamp
		}
	}

	for _, message := range messages {
		viewed := latest[[2]int64{int64(message.TypeID), message.ItemID}]
		result[message.ID] = !viewed.Valid || viewed.Time.Before(message.CreatedAt)
	}

	return result, nil
}
