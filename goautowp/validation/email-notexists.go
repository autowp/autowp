package validation

import (
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

// EmailNotExists validator.
type EmailNotExists struct {
	DB *goqu.Database
}

// IsValidString IsValidString.
func (s *EmailNotExists) IsValidString(value string) ([]string, error) {
	var exists bool

	success, err := s.DB.Select(goqu.V(1)).
		From(schema.UserTable).
		Where(schema.UserTableEmailCol.Eq(value)).
		ScanVal(&exists)
	if err != nil {
		return nil, err
	}

	if !success {
		return []string{}, nil
	}

	return []string{EmailNotExistsExists}, nil
}

// IsValidInt32 IsValidInt32.
func (s *EmailNotExists) IsValidInt32(int32) ([]string, error) {
	return nil, nil
}
