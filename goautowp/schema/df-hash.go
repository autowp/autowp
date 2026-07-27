package schema

import "github.com/doug-martin/goqu/v9"

// The hash column stores a 256-bit PDQ perceptual hash as a native
// Postgres bit(256) value, so its Hamming distance can be computed on the
// Postgres side with the native bitwise XOR (#) operator and bit_count().
// See migration 28_df-hash-pdq.
const (
	DfHashTableName             = "df_hash"
	DfHashTableHashColName      = "hash"
	DfHashTablePictureIDColName = "picture_id"
)

var (
	DfHashTable             = goqu.T(DfHashTableName)
	DfHashTableHashCol      = DfHashTable.Col(DfHashTableHashColName)
	DfHashTablePictureIDCol = DfHashTable.Col(DfHashTablePictureIDColName)
)
