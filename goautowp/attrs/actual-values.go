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
	typeID      schema.AttrsAttributeTypeID
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

// ActualValuesText preserves ActualValueText semantics, including SQL NULLs,
// missing typed rows, TEXT attributes and list ordering. Specification-table
// batches deliberately omit NULLs and therefore cannot be used here directly.
func (s *Repository) ActualValuesText(
	ctx context.Context, rows []schema.AttrsValueRow, lang string,
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

		groups[table] = append(groups[table], actualValueRequest{
			index: index, itemID: row.ItemID, attributeID: row.AttributeID, typeID: attribute.TypeID.AttributeTypeID,
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
	case schema.AttrsValuesStringTableName:
		rows, err := scanActualValueRows[schema.AttrsValuesStringRow](ctx, s.db, table, requests, false)
		if err != nil {
			return err
		}

		values := make(map[[2]int64]sql.NullString, len(rows))

		for _, row := range rows {
			values[[2]int64{row.ItemID, row.AttributeID}] = row.Value
		}

		for _, request := range requests {
			value, found := values[[2]int64{request.itemID, request.attributeID}]
			result[request.index].Value = Value{
				Valid: found, Type: request.typeID, IsEmpty: !value.Valid, StringValue: value.String,
			}
		}
	case schema.AttrsValuesFloatTableName:
		rows, err := scanActualValueRows[schema.AttrsValuesFloatRow](ctx, s.db, table, requests, false)
		if err != nil {
			return err
		}

		values := make(map[[2]int64]sql.NullFloat64, len(rows))

		for _, row := range rows {
			values[[2]int64{row.ItemID, row.AttributeID}] = row.Value
		}

		for _, request := range requests {
			value, found := values[[2]int64{request.itemID, request.attributeID}]
			result[request.index].Value = Value{
				Valid: found, Type: request.typeID, IsEmpty: !value.Valid, FloatValue: value.Float64,
			}
		}
	case schema.AttrsValuesIntTableName:
		rows, err := scanActualValueRows[schema.AttrsValuesIntRow](ctx, s.db, table, requests, false)
		if err != nil {
			return err
		}

		values := make(map[[2]int64]sql.NullInt32, len(rows))

		for _, row := range rows {
			values[[2]int64{row.ItemID, row.AttributeID}] = row.Value
		}

		for _, request := range requests {
			value, found := values[[2]int64{request.itemID, request.attributeID}]

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
	case schema.AttrsValuesListTableName:
		rows, err := scanActualValueRows[schema.AttrsValuesListRow](ctx, s.db, table, requests, true)
		if err != nil {
			return err
		}

		values := make(map[[2]int64][]sql.NullInt64)

		for _, row := range rows {
			key := [2]int64{row.ItemID, row.AttributeID}
			values[key] = append(values[key], row.Value)
		}

		for _, request := range requests {
			list := values[[2]int64{request.itemID, request.attributeID}]

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
	itemIDs := make([]int64, 0, len(requests))

	attributeIDs := make([]int64, 0, len(requests))
	for _, request := range requests {
		itemIDs = append(itemIDs, request.itemID)
		attributeIDs = append(attributeIDs, request.attributeID)
	}

	source := goqu.T(table)

	query := db.From(source).Select(source.Col(schema.AttrsValuesTableItemIDColName),
		source.Col(schema.AttrsValuesTableAttributeIDColName), source.Col(schema.AttrsValuesStringTableValueColName)).
		Where(source.Col(schema.AttrsValuesTableItemIDColName).In(itemIDs),
			source.Col(schema.AttrsValuesTableAttributeIDColName).In(attributeIDs))
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
