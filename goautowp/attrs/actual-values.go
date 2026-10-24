package attrs

import (
	"context"
	"database/sql"
	"fmt"
	"time"

	"github.com/autowp/goautowp/logging"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/util"
	"github.com/doug-martin/goqu/v9"
	"github.com/lib/pq"
	"golang.org/x/sync/errgroup"
)

const actualValueReadParallelism = 4

// ValueText pairs a typed value with its localized representation.
type ValueText struct {
	Value Value
	Text  string
}

type actualValueRequest struct {
	index       int
	itemID      int64
	attributeID int64
	userID      int64
	typeID      schema.AttrsAttributeTypeID
}

type typedValueRow[T any] struct {
	ItemID      int64 `db:"item_id"`
	AttributeID int64 `db:"attribute_id"`
	UserID      int64 `db:"user_id"`
	Value       T     `db:"value"`
}

func runActualValueLookups(ctx context.Context, lookups []func(context.Context) error) error {
	group, lookupCtx := errgroup.WithContext(ctx)
	group.SetLimit(actualValueReadParallelism)

	for _, lookup := range lookups {
		group.Go(func() error {
			if err := lookupCtx.Err(); err != nil {
				return err
			}

			return lookup(lookupCtx)
		})
	}

	return group.Wait()
}

// ActualValuesText preserves typed values, SQL NULLs and list ordering.
func (s *Repository) ActualValuesText(
	ctx context.Context, rows []schema.AttrsValueRow, lang string,
) ([]ValueText, error) {
	requests := make([]schema.AttrsUserValueRow, len(rows))
	for index, row := range rows {
		requests[index] = schema.AttrsUserValueRow{ItemID: row.ItemID, AttributeID: row.AttributeID}
	}

	return s.valuesText(ctx, requests, lang, true, false)
}

// UserValues batches typed user values and optionally formats them.
func (s *Repository) UserValues(
	ctx context.Context, rows []schema.AttrsUserValueRow, lang string, withText bool,
) ([]ValueText, error) {
	return s.valuesText(ctx, rows, lang, withText, true)
}

func (s *Repository) valuesText(
	ctx context.Context, rows []schema.AttrsUserValueRow, lang string, withText bool, userValues bool,
) ([]ValueText, error) {
	result := make([]ValueText, len(rows))
	groups := make(map[string][]actualValueRequest)

	for index, row := range rows {
		attribute, err := s.Attribute(ctx, row.AttributeID)
		if err != nil {
			return nil, err
		}

		if attribute == nil {
			return nil, fmt.Errorf("%w: `%d`", errAttributeNotFound, row.AttributeID)
		}

		if !attribute.TypeID.Valid {
			continue
		}

		var table string

		switch attribute.TypeID.AttributeTypeID {
		case schema.AttrsAttributeTypeIDString, schema.AttrsAttributeTypeIDText:
			table = schema.AttrsValuesStringTableName
		case schema.AttrsAttributeTypeIDInteger, schema.AttrsAttributeTypeIDBoolean:
			table = schema.AttrsValuesIntTableName
		case schema.AttrsAttributeTypeIDFloat:
			table = schema.AttrsValuesFloatTableName
		case schema.AttrsAttributeTypeIDList, schema.AttrsAttributeTypeIDTree:
			table = schema.AttrsValuesListTableName
		case schema.AttrsAttributeTypeIDUnknown:
			continue
		default:
			continue
		}

		if userValues {
			switch table {
			case schema.AttrsValuesStringTableName:
				table = schema.AttrsUserValuesStringTableName
			case schema.AttrsValuesIntTableName:
				table = schema.AttrsUserValuesIntTableName
			case schema.AttrsValuesFloatTableName:
				table = schema.AttrsUserValuesFloatTableName
			case schema.AttrsValuesListTableName:
				table = schema.AttrsUserValuesListTableName
			}
		}

		groups[table] = append(groups[table], actualValueRequest{
			index:       index,
			itemID:      row.ItemID,
			attributeID: row.AttributeID,
			typeID:      attribute.TypeID.AttributeTypeID,
			userID:      row.UserID,
		})
	}

	lookups := make([]func(context.Context) error, 0, len(groups))
	for table, requests := range groups {
		lookups = append(lookups, func(ctx context.Context) error {
			return s.loadActualValueGroup(ctx, table, requests, result)
		})
	}

	if err := runActualValueLookups(ctx, lookups); err != nil {
		return nil, err
	}

	if !withText {
		return result, nil
	}

	for index, row := range rows {
		text, err := s.valueToText(ctx, row.AttributeID, result[index].Value, lang)
		if err != nil {
			return nil, err
		}

		result[index].Text = text
	}

	return result, nil
}

func (s *Repository) loadActualValueGroup(
	ctx context.Context, table string, requests []actualValueRequest, result []ValueText,
) error {
	switch table {
	case schema.AttrsValuesStringTableName, schema.AttrsUserValuesStringTableName:
		rows, err := scanActualValueRows[typedValueRow[sql.NullString]](ctx, s.db, table, requests, false)
		if err != nil {
			return err
		}

		values := make(map[[3]int64]sql.NullString, len(rows))

		for _, row := range rows {
			values[[3]int64{row.ItemID, row.AttributeID, row.UserID}] = row.Value
		}

		for _, request := range requests {
			value, found := values[[3]int64{request.itemID, request.attributeID, request.userID}]
			result[request.index].Value = Value{
				Valid: found, Type: request.typeID, IsEmpty: !value.Valid, StringValue: value.String,
			}
		}
	case schema.AttrsValuesFloatTableName, schema.AttrsUserValuesFloatTableName:
		rows, err := scanActualValueRows[typedValueRow[sql.NullFloat64]](ctx, s.db, table, requests, false)
		if err != nil {
			return err
		}

		values := make(map[[3]int64]sql.NullFloat64, len(rows))

		for _, row := range rows {
			values[[3]int64{row.ItemID, row.AttributeID, row.UserID}] = row.Value
		}

		for _, request := range requests {
			value, found := values[[3]int64{request.itemID, request.attributeID, request.userID}]
			result[request.index].Value = Value{
				Valid: found, Type: request.typeID, IsEmpty: !value.Valid, FloatValue: value.Float64,
			}
		}
	case schema.AttrsValuesIntTableName, schema.AttrsUserValuesIntTableName:
		rows, err := scanActualValueRows[typedValueRow[sql.NullInt32]](ctx, s.db, table, requests, false)
		if err != nil {
			return err
		}

		values := make(map[[3]int64]sql.NullInt32, len(rows))

		for _, row := range rows {
			values[[3]int64{row.ItemID, row.AttributeID, row.UserID}] = row.Value
		}

		for _, request := range requests {
			value, found := values[[3]int64{request.itemID, request.attributeID, request.userID}]

			extracted := Value{Valid: found, Type: request.typeID, IsEmpty: !value.Valid}
			if request.typeID == schema.AttrsAttributeTypeIDBoolean {
				var boolean sql.NullBool
				if value.Valid {
					if err := boolean.Scan(int64(value.Int32)); err != nil {
						return err
					}
				}

				extracted.BoolValue = boolean.Bool
			} else {
				extracted.IntValue = value.Int32
			}

			result[request.index].Value = extracted
		}
	case schema.AttrsValuesListTableName, schema.AttrsUserValuesListTableName:
		rows, err := scanActualValueRows[typedValueRow[sql.NullInt64]](ctx, s.db, table, requests, true)
		if err != nil {
			return err
		}

		values := make(map[[3]int64][]sql.NullInt64)

		for _, row := range rows {
			key := [3]int64{row.ItemID, row.AttributeID, row.UserID}
			values[key] = append(values[key], row.Value)
		}

		for _, request := range requests {
			list := values[[3]int64{request.itemID, request.attributeID, request.userID}]

			value := Value{Type: request.typeID, ListValue: make([]int64, 0, len(list))}
			for _, entry := range list {
				if !entry.Valid {
					value.IsEmpty = true

					break
				}

				value.ListValue = append(value.ListValue, entry.Int64)
			}

			value.Valid = len(value.ListValue) > 0 || value.IsEmpty
			result[request.index].Value = value
		}
	}

	return nil
}

func scanActualValueRows[T any](
	ctx context.Context, db *goqu.Database, table string, requests []actualValueRequest, ordered bool,
) ([]T, error) {
	if len(requests) == 0 {
		return []T{}, nil
	}

	userValues := false

	switch table {
	case schema.AttrsUserValuesStringTableName, schema.AttrsUserValuesIntTableName,
		schema.AttrsUserValuesFloatTableName, schema.AttrsUserValuesListTableName:
		userValues = true
	}

	itemIDs := make([]int64, 0, len(requests))
	attributeIDs := make([]int64, 0, len(requests))
	userIDs := make([]int64, 0, len(requests))

	seen := make(map[[3]int64]bool, len(requests))
	for _, request := range requests {
		key := [3]int64{request.itemID, request.attributeID, 0}
		if userValues {
			key[2] = request.userID
		}

		if seen[key] {
			continue
		}

		seen[key] = true
		itemIDs = append(itemIDs, key[0])
		attributeIDs = append(attributeIDs, key[1])
		userIDs = append(userIDs, key[2])
	}

	// Parallel arrays describe exact keys, not every combination of their parts.
	// Deduplication prevents repeated requests from duplicating list values.
	source := goqu.T(table)
	requested := goqu.T("requested")
	keys := goqu.L("unnest(?::bigint[], ?::bigint[], ?::bigint[]) AS requested(item_id, attribute_id, user_id)",
		pq.Array(itemIDs), pq.Array(attributeIDs), pq.Array(userIDs))

	condition := goqu.And(
		source.Col(schema.AttrsValuesTableItemIDColName).Eq(requested.Col(schema.AttrsValuesTableItemIDColName)),
		source.Col(schema.AttrsValuesTableAttributeIDColName).
			Eq(requested.Col(schema.AttrsValuesTableAttributeIDColName)),
	)
	if userValues {
		condition = goqu.And(
			condition,
			source.Col(schema.AttrsUserValuesTypeTableUserIDColName).
				Eq(requested.Col(schema.AttrsUserValuesTypeTableUserIDColName)),
		)
	}

	query := db.From(source).Join(keys, goqu.On(condition)).Select(
		source.Col(schema.AttrsValuesTableItemIDColName),
		source.Col(schema.AttrsValuesTableAttributeIDColName),
		source.Col(schema.AttrsValuesStringTableValueColName))
	if userValues {
		query = query.SelectAppend(source.Col(schema.AttrsUserValuesTypeTableUserIDColName))
	} else {
		query = query.SelectAppend(goqu.L("0").As(schema.AttrsUserValuesTypeTableUserIDColName))
	}

	if ordered {
		query = query.Order(source.Col(schema.AttrsValuesListTableOrderingColName).Asc())
	}

	// Scalar reads retain the retry behavior of ActualValue. List reads have
	// always issued a single query and keep that behavior.
	attempts := 1
	if !ordered {
		attempts = 10
	}

	var err error

	for range attempts {
		rows := make([]T, 0)

		err = query.ScanStructsContext(ctx, &rows)
		if err == nil || ordered || !util.IsPgDeadlockError(err) {
			return rows, err
		}

		logging.Warn("Deadlock detected. Retrying")
		time.Sleep(time.Millisecond)
	}

	return nil, err
}
