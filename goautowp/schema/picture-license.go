package schema

import "github.com/doug-martin/goqu/v9"

type PictureLicense int16

const (
	PictureLicenseUnknown           PictureLicense = 0
	PictureLicenseAllRightsReserved PictureLicense = 1
	PictureLicenseCC0               PictureLicense = 2
	PictureLicenseCCBY              PictureLicense = 3
	PictureLicenseCCBYSA            PictureLicense = 4
	PictureLicenseCCBYNC            PictureLicense = 5
	PictureLicenseCCBYNCSA          PictureLicense = 6
	PictureLicenseCCBYND            PictureLicense = 7
	PictureLicenseCCBYNCND          PictureLicense = 8
	PictureLicensePublicDomain      PictureLicense = 9

	PictureLicenseTableName        = "picture_license"
	PictureLicenseTableIDColName   = "id"
	PictureLicenseTableNameColName = "name"
)

var (
	PictureLicenseTable        = goqu.T(PictureLicenseTableName)
	PictureLicenseTableIDCol   = PictureLicenseTable.Col(PictureLicenseTableIDColName)
	PictureLicenseTableNameCol = PictureLicenseTable.Col(PictureLicenseTableNameColName)
)

type PictureLicenseRow struct {
	ID   int16  `db:"id"`
	Name string `db:"name"`
}
