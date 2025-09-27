package goautowp

import (
	"fmt"
	"math/rand"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/itemofday"
	"github.com/autowp/goautowp/schema"
	"github.com/stretchr/testify/require"
)

func TestYoomoneyWebhookInvalidLabel(t *testing.T) {
	t.Parallel()

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	itemOfDayRepository := itemofday.NewRepository(goquDB)

	ctx := t.Context()

	yh, err := NewYoomoneyHandler("0.99", "01234567890ABCDEF01234567890", itemOfDayRepository)
	require.NoError(t, err)

	err = yh.Handle(ctx, YoomoneyWebhook{
		NotificationType: "p2p-incoming",
		OperationID:      "1234567",
		Amount:           "300.00",
		WithdrawAmount:   "1.00",
		Currency:         "643",
		Datetime:         "2011-07-01T09:00:00.000+04:00",
		Sender:           "41001XXXXXXXX",
		Codepro:          "false",
		Label:            "YM.label.12345",
		SHA1Hash:         "a2ee4a9195f4a90e893cff4f62eeba0b662321f9",
		TestNotification: false,
		Unaccepted:       false,
	})

	require.ErrorContains(t, err, "label not matched by regular expression")
}

func TestYoomoneyWebhookHappyPath(t *testing.T) {
	t.Parallel()

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, "frontend", "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	itemOfDayRepository := itemofday.NewRepository(goquDB)
	itemOfDayRepository.SetMinPictures(0)

	yh, err := NewYoomoneyHandler("0.99", "01234567890ABCDEF01234567890", itemOfDayRepository)
	require.NoError(t, err)

	dateStr := time.Now().Format(itemofday.YoomoneyLabelDateFormat)

	// prepare test data
	_, err = goquDB.Delete(schema.OfDayTable).
		Where(schema.OfDayTableDayDateCol.Eq(dateStr)).
		Executor().
		ExecContext(ctx)
	require.NoError(t, err)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	itemID := createItem(t, conn, cnt, &APIItem{
		Name:            fmt.Sprintf("item-of-day-%d", random.Int()),
		IsGroup:         true,
		ItemTypeId:      ItemType_ITEM_TYPE_BRAND,
		Catname:         fmt.Sprintf("brand1-%d", random.Int()),
		Body:            "",
		ProducedExactly: false,
	})

	addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{
		ItemID: itemID,
	}, PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)

	// check prepared item passes candidate checks
	r := itemofday.CandidateRecord{}

	sqSelect := itemOfDayRepository.CandidateQuery()
	_, err = sqSelect.Limit(1).Executor().ScanStructContext(ctx, &r)
	require.NoError(t, err)
	require.NotZero(t, itemID)

	label := fmt.Sprintf("vod/%s/%d/0", dateStr, itemID)

	fields := YoomoneyWebhook{
		NotificationType: "p2p-incoming",
		OperationID:      "1234567",
		Amount:           "300.00",
		WithdrawAmount:   "1.00",
		Currency:         "643",
		Datetime:         "2011-07-01T09:00:00.000+04:00",
		Sender:           "41001XXXXXXXX",
		Codepro:          "false",
		Label:            label,
		TestNotification: false,
		Unaccepted:       false,
	}
	fields.SHA1Hash, err = yh.Hash(fields)
	require.NoError(t, err)

	err = yh.Handle(ctx, fields)

	require.NoError(t, err)
}
