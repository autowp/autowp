package goautowp

import (
	"context"
	"crypto/sha1" //nolint:gosec
	"encoding/hex"
	"errors"
	"fmt"
	"net/http"
	"regexp"
	"strconv"
	"strings"
	"time"

	"github.com/autowp/goautowp/itemofday"
	"github.com/gin-gonic/gin"
	decimal2 "github.com/shopspring/decimal"
	"github.com/sirupsen/logrus"
)

var (
	errNotificationSecretNotConfigured  = errors.New("notification secret not configured")
	errFailedToSetItemOfDay             = errors.New("failed to set item of day")
	errUnacceptedPayment                = errors.New("unaccepted payment")
	errDateNotAvailable                 = errors.New("date is not available")
	errItemIDCantBeZero                 = errors.New("item_id can't be 0")
	errSha1NotProvided                  = errors.New("sha1_hash not provided")
	errSha1NotMatched                   = errors.New("sha1 hash not matched")
	errOnlyRubIsSupported               = errors.New("only RUB currency is supported")
	errPriceIsGreaterThanWithdrawAmount = errors.New("price is greater than withdraw_amount")
	errLabelNotMatchedByRegexp          = errors.New("label not matched by regular expression")
)

type YoomoneyHandler struct {
	price              decimal2.Decimal
	notificationSecret string
	itemOfDay          *itemofday.Repository
}

func NewYoomoneyHandler(
	price string,
	notificationSecret string,
	itemOfDay *itemofday.Repository,
) (*YoomoneyHandler, error) {
	decPrice, err := decimal2.NewFromString(price)
	if err != nil {
		return nil, err
	}

	return &YoomoneyHandler{
		price:              decPrice,
		notificationSecret: notificationSecret,
		itemOfDay:          itemOfDay,
	}, nil
}

const RUB = "643"

type YoomoneyWebhook struct {
	NotificationType string `binding:"required" form:"notification_type" json:"notification_type"` //nolint: tagalign
	OperationID      string `binding:"required" form:"operation_id"      json:"operation_id"`      //nolint: tagalign
	Amount           string `binding:"required" form:"amount"            json:"amount"`            //nolint: tagalign
	WithdrawAmount   string `                   form:"withdraw_amount"   json:"withdraw_amount"`   //nolint: tagalign
	Currency         string `binding:"required" form:"currency"          json:"currency"`          //nolint: tagalign
	Datetime         string `binding:"required" form:"datetime"          json:"datetime"`          //nolint: tagalign
	Sender           string `                   form:"sender"            json:"sender"`            //nolint: tagalign
	Codepro          string `binding:"required" form:"codepro"           json:"codepro"`           //nolint: tagalign
	Label            string `                   form:"label"             json:"label"`             //nolint: tagalign
	SHA1Hash         string `binding:"required" form:"sha1_hash"         json:"sha1_hash"`         //nolint: tagalign
	TestNotification bool   `                   form:"test_notification" json:"test_notification"` //nolint: tagalign
	Unaccepted       bool   `                   form:"unaccepted"        json:"unaccepted"`        //nolint: tagalign
}

func (s *YoomoneyHandler) Hash(fields YoomoneyWebhook) (string, error) {
	if s.notificationSecret == "" {
		return "", errNotificationSecretNotConfigured
	}

	str := strings.Join([]string{
		fields.NotificationType,
		fields.OperationID,
		fields.Amount,
		fields.Currency,
		fields.Datetime,
		fields.Sender,
		fields.Codepro,
		s.notificationSecret,
		fields.Label,
	}, "&")

	h := sha1.New() //nolint:gosec
	h.Write([]byte(str))
	sha1hash := h.Sum(nil)

	return hex.EncodeToString(sha1hash[0:sha1.Size]), nil
}

func (s *YoomoneyHandler) Handle(ctx context.Context, fields YoomoneyWebhook) error {
	sha1hashStr, err := s.Hash(fields)
	if err != nil {
		return err
	}

	if fields.SHA1Hash == "" {
		return errSha1NotProvided
	}

	if !strings.EqualFold(sha1hashStr, fields.SHA1Hash) {
		return errSha1NotMatched
	}

	if fields.Currency != RUB {
		return errOnlyRubIsSupported
	}

	withdrawAmount, err := decimal2.NewFromString(fields.WithdrawAmount)
	if err != nil {
		return err
	}

	if s.price.GreaterThan(withdrawAmount) {
		return errPriceIsGreaterThanWithdrawAmount
	}

	re := regexp.MustCompile(`^vod/(\d{4}-\d{2}-\d{2})/(\d+)/(\d+)$`)

	matches := re.FindStringSubmatch(fields.Label)
	if len(matches) < 2 {
		return errLabelNotMatchedByRegexp
	}

	dateTime, err := time.Parse(itemofday.YoomoneyLabelDateFormat, matches[1])
	if err != nil {
		return fmt.Errorf("failed to parse date in label: %w", err)
	}

	IsAvailableDate, err := s.itemOfDay.IsAvailableDate(ctx, dateTime)
	if err != nil {
		return err
	}

	if !IsAvailableDate {
		return errDateNotAvailable
	}

	itemID, err := strconv.ParseInt(matches[2], 10, 0)
	if err != nil {
		return err
	}

	userID, err := strconv.ParseInt(matches[3], 10, 0)
	if err != nil {
		return err
	}

	if itemID == 0 {
		return errItemIDCantBeZero
	}

	if fields.Unaccepted {
		return errUnacceptedPayment
	}

	success, err := s.itemOfDay.SetItemOfDay(ctx, dateTime, itemID, userID)
	if err != nil {
		return err
	}

	if !success {
		return errFailedToSetItemOfDay
	}

	return nil
}

func (s *YoomoneyHandler) SetupRouter(_ context.Context, r *gin.Engine) {
	r.POST("/yoomoney/informing", func(ctx *gin.Context) { //nolint: contextcheck
		var fields YoomoneyWebhook

		if err := ctx.ShouldBind(&fields); err != nil {
			logrus.Warnf("yoomoney bad request: %s", err.Error())
			ctx.Status(http.StatusBadRequest)

			return
		}

		if err := s.Handle(ctx, fields); err != nil {
			logrus.Warnf("yoomoney: %s", err.Error())
			ctx.String(http.StatusInternalServerError, err.Error())

			return
		}

		logrus.Info("yoomoney: success")
		ctx.Status(http.StatusOK)
	})
}
