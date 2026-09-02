package goautowp

import (
	"context"
	"errors"
	"fmt"
	"net"

	"github.com/autowp/goautowp/comments"
	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/contentreport"
	"github.com/autowp/goautowp/feedback"
	"github.com/autowp/goautowp/image/storage"
	"github.com/autowp/goautowp/validation"
	"github.com/grpc-ecosystem/go-grpc-middleware/v2/interceptors/logging"
	"github.com/sirupsen/logrus"
	"google.golang.org/genproto/googleapis/rpc/errdetails"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
)

const (
	feedbackMessageField = "message"
	feedbackNameField    = "name"
)

var errNoEndpointProvided = errors.New("no endpoints provided")

func APIImageToGRPC(image *storage.Image) *Image {
	if image == nil {
		return nil
	}

	return &Image{ //nolint:exhaustruct
		Id:         int32(image.ID()), //nolint: gosec
		Src:        image.Src(),
		Width:      int32(image.Width()),    //nolint: gosec
		Height:     int32(image.Height()),   //nolint: gosec
		Filesize:   int32(image.FileSize()), //nolint: gosec
		CropLeft:   int32(image.CropLeft()),
		CropTop:    int32(image.CropTop()),
		CropWidth:  int32(image.CropWidth()),
		CropHeight: int32(image.CropHeight()),
	}
}

type GRPCServer struct {
	UnimplementedAutowpServer

	auth            *Auth
	reCaptchaConfig config.RecaptchaConfig
	comments        *comments.Repository
	ipExtractor     *IPExtractor
	feedback        *feedback.Repository
	contentReports  *contentreport.Repository
	captchaEnabled  bool
}

func NewGRPCServer(
	auth *Auth,
	reCaptchaConfig config.RecaptchaConfig,
	comments *comments.Repository,
	ipExtractor *IPExtractor,
	feedback *feedback.Repository,
	contentReports *contentreport.Repository,
	captchaEnabled bool,
) *GRPCServer {
	return &GRPCServer{ //nolint:exhaustruct
		auth:            auth,
		reCaptchaConfig: reCaptchaConfig,
		comments:        comments,
		ipExtractor:     ipExtractor,
		feedback:        feedback,
		contentReports:  contentReports,
		captchaEnabled:  captchaEnabled,
	}
}

func (s *GRPCServer) GetReCaptchaConfig(context.Context, *emptypb.Empty) (*ReCaptchaConfig, error) {
	return &ReCaptchaConfig{ //nolint:exhaustruct
		PublicKey: s.reCaptchaConfig.PublicKey,
	}, nil
}

func (s *GRPCServer) GetIP(ctx context.Context, in *GetIPRequest) (*IP, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, s.auth.GRPCError(err)
	}

	// An empty address means "my own IP" - what the Access denied page asks for to show a banned
	// visitor why. This method is exempt from the ban interceptor for the same reason.
	ip := userCtx.IP
	if in.GetIpAddress() != "" {
		ip = net.ParseIP(in.GetIpAddress())
	}

	if ip == nil {
		return nil, status.Errorf(codes.InvalidArgument, "invalid argument")
	}

	m := make(map[string]bool)
	for _, e := range in.GetFields() {
		m[e] = true
	}

	result, err := s.ipExtractor.Extract(ctx, ip, m, userCtx.UserID, userCtx.Roles)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return result, nil
}

func (s *GRPCServer) CreateFeedback(
	ctx context.Context,
	in *CreateFeedbackRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, s.auth.GRPCError(err)
	}

	fb := in.GetFeedback()

	fv, err := fb.Validate(s.captchaEnabled, userCtx.IP.String())
	if err != nil {
		return nil, err
	}

	if len(fv) > 0 {
		return nil, wrapFieldViolations(fv)
	}

	err = s.feedback.Create(feedback.CreateFeedbackRequest{
		Name:    fb.GetName(),
		Email:   fb.GetEmail(),
		Message: fb.GetMessage(),
		Captcha: fb.GetCaptcha(),
		IP:      userCtx.IP.String(),
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func wrapFieldViolations(fv []*errdetails.BadRequest_FieldViolation) error {
	st := status.New(codes.InvalidArgument, "invalid request")
	br := &errdetails.BadRequest{
		FieldViolations: fv,
	}

	st, err := st.WithDetails(br)
	if err != nil {
		return status.Error(codes.Internal, err.Error())
	}

	return st.Err()
}

func (s *GRPCServer) GetTimezones(context.Context, *emptypb.Empty) (*Timezones, error) {
	return &Timezones{Timezones: TimeZones()}, nil
}

func InterceptorLogger(fieldLogger logrus.FieldLogger) logging.Logger {
	return logging.LoggerFunc(
		func(_ context.Context, lvl logging.Level, msg string, fields ...any) {
			fieldsMap := make(map[string]any, len(fields)/2)
			i := logging.Fields(fields).Iterator()

			for i.Next() {
				k, v := i.At()
				fieldsMap[k] = v
			}

			entry := fieldLogger.WithFields(fieldsMap)

			switch lvl {
			case logging.LevelDebug:
				entry.Debug(msg)
			case logging.LevelInfo:
				entry.Info(msg)
			case logging.LevelWarn:
				entry.Warn(msg)
			case logging.LevelError:
				entry.Error(msg)
			default:
				panic(fmt.Sprintf("unknown level %v", lvl))
			}
		},
	)
}

func (s *Feedback) Validate(
	captchaEnabled bool,
	ip string,
) ([]*errdetails.BadRequest_FieldViolation, error) {
	var (
		result   = make([]*errdetails.BadRequest_FieldViolation, 0)
		problems []string
		err      error
	)

	nameInputFilter := validation.InputFilter{
		Filters:    []validation.FilterInterface{&validation.StringTrimFilter{}},
		Validators: []validation.ValidatorInterface{&validation.NotEmpty{}},
	}

	s.Name, problems, err = nameInputFilter.IsValidString(s.GetName())
	if err != nil {
		return nil, err
	}

	for _, fv := range problems {
		result = append(result, &errdetails.BadRequest_FieldViolation{
			Field:       feedbackNameField,
			Description: fv,
		})
	}

	emailInputFilter := validation.InputFilter{
		Filters: []validation.FilterInterface{&validation.StringTrimFilter{}},
		Validators: []validation.ValidatorInterface{
			&validation.NotEmpty{},
			&validation.EmailAddress{},
		},
	}

	s.Email, problems, err = emailInputFilter.IsValidString(s.GetEmail())
	if err != nil {
		return nil, err
	}

	for _, fv := range problems {
		result = append(result, &errdetails.BadRequest_FieldViolation{
			Field:       "email",
			Description: fv,
		})
	}

	messageInputFilter := validation.InputFilter{
		Filters:    []validation.FilterInterface{&validation.StringTrimFilter{}},
		Validators: []validation.ValidatorInterface{&validation.NotEmpty{}},
	}

	s.Message, problems, err = messageInputFilter.IsValidString(s.GetMessage())
	if err != nil {
		return nil, err
	}

	for _, fv := range problems {
		result = append(result, &errdetails.BadRequest_FieldViolation{
			Field:       feedbackMessageField,
			Description: fv,
		})
	}

	if captchaEnabled {
		captchaInputFilter := validation.InputFilter{
			Filters: []validation.FilterInterface{&validation.StringTrimFilter{}},
			Validators: []validation.ValidatorInterface{
				&validation.NotEmpty{},
				&validation.Recaptcha{
					ClientIP: ip,
				},
			},
		}

		s.Captcha, problems, err = captchaInputFilter.IsValidString(s.GetCaptcha())
		if err != nil {
			return nil, err
		}

		for _, fv := range problems {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "captcha",
				Description: fv,
			})
		}
	}

	return result, nil
}
