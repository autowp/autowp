package autowp

import (
	"context"
	"net"

	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/audit"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/comments"
	"github.com/autowp/goautowp/internal/compliance"
	"github.com/autowp/goautowp/internal/config"
	"github.com/autowp/goautowp/internal/contentreport"
	"github.com/autowp/goautowp/internal/feedback"
	"github.com/autowp/goautowp/internal/pictures"
	"github.com/autowp/goautowp/internal/timezones"
	"github.com/autowp/goautowp/internal/transport/grpc/grpcerrors"
	"github.com/autowp/goautowp/internal/transport/grpc/ipextractor"
	"github.com/autowp/goautowp/internal/validation"
	"google.golang.org/genproto/googleapis/rpc/errdetails"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
)

const (
	feedbackMessageField = "message"
	feedbackNameField    = "name"
)

type Server struct {
	pb.UnimplementedAutowpServer

	auth                 *authn.Service
	reCaptchaConfig      config.RecaptchaConfig
	comments             *comments.Repository
	ipExtractor          *ipextractor.Extractor
	feedback             *feedback.Repository
	contentReports       *contentreport.Repository
	complianceRepository *compliance.Repository
	picturesRepository   *pictures.Repository
	events               *audit.Recorder
	captchaEnabled       bool
}

func NewServer(
	auth *authn.Service,
	reCaptchaConfig config.RecaptchaConfig,
	comments *comments.Repository,
	ipExtractor *ipextractor.Extractor,
	feedback *feedback.Repository,
	contentReports *contentreport.Repository,
	complianceRepository *compliance.Repository,
	picturesRepository *pictures.Repository,
	events *audit.Recorder,
	captchaEnabled bool,
) *Server {
	return &Server{ //nolint:exhaustruct
		auth:                 auth,
		reCaptchaConfig:      reCaptchaConfig,
		comments:             comments,
		ipExtractor:          ipExtractor,
		feedback:             feedback,
		contentReports:       contentReports,
		complianceRepository: complianceRepository,
		picturesRepository:   picturesRepository,
		events:               events,
		captchaEnabled:       captchaEnabled,
	}
}

func (s *Server) GetReCaptchaConfig(context.Context, *emptypb.Empty) (*pb.ReCaptchaConfig, error) {
	return &pb.ReCaptchaConfig{ //nolint:exhaustruct
		PublicKey: s.reCaptchaConfig.PublicKey,
	}, nil
}

func (s *Server) GetIP(ctx context.Context, in *pb.GetIPRequest) (*pb.IP, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
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

func (s *Server) CreateFeedback(
	ctx context.Context,
	in *pb.CreateFeedbackRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	fb := in.GetFeedback()

	fv, err := validateFeedback(fb, s.captchaEnabled, userCtx.IP.String())
	if err != nil {
		return nil, err
	}

	if len(fv) > 0 {
		return nil, grpcerrors.FieldViolations(fv)
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

func (s *Server) GetTimezones(context.Context, *emptypb.Empty) (*pb.Timezones, error) {
	return &pb.Timezones{Timezones: timezones.List()}, nil
}

func validateFeedback(
	feedback *pb.Feedback,
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

	feedback.Name, problems, err = nameInputFilter.IsValidString(feedback.GetName())
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

	feedback.Email, problems, err = emailInputFilter.IsValidString(feedback.GetEmail())
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

	feedback.Message, problems, err = messageInputFilter.IsValidString(feedback.GetMessage())
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

		feedback.Captcha, problems, err = captchaInputFilter.IsValidString(feedback.GetCaptcha())
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
