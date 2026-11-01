package schema

import (
	"github.com/doug-martin/goqu/v9"
	"github.com/doug-martin/goqu/v9/exp"
)

func Excluded(i string) exp.LiteralExpression { //nolint: ireturn
	return goqu.L("EXCLUDED." + i)
}
