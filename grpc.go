package goautowp

import (
	"context"
	"errors"
	"fmt"
	"net"
	"net/url"

	"github.com/autowp/goautowp/comments"
	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/image/storage"
	"github.com/autowp/goautowp/users"
	"github.com/autowp/goautowp/util"
	"github.com/grpc-ecosystem/go-grpc-middleware/v2/interceptors/logging"
	"github.com/sirupsen/logrus"
	"google.golang.org/genproto/googleapis/rpc/errdetails"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
)

var errNoEndpointProvided = errors.New("no endpoints provided")

func APIImageToGRPC(image *storage.Image) *APIImage {
	if image == nil {
		return nil
	}

	return &APIImage{ //nolint:exhaustruct
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

	auth              *Auth
	catalogue         *Catalogue
	reCaptchaConfig   config.RecaptchaConfig
	fileStorageConfig config.FileStorageConfig
	comments          *comments.Repository
	ipExtractor       *IPExtractor
	feedback          *Feedback
}

func NewGRPCServer(
	auth *Auth,
	catalogue *Catalogue,
	reCaptchaConfig config.RecaptchaConfig,
	fileStorageConfig config.FileStorageConfig,
	comments *comments.Repository,
	ipExtractor *IPExtractor,
	feedback *Feedback,
) *GRPCServer {
	return &GRPCServer{ //nolint:exhaustruct
		auth:              auth,
		catalogue:         catalogue,
		reCaptchaConfig:   reCaptchaConfig,
		fileStorageConfig: fileStorageConfig,
		comments:          comments,
		ipExtractor:       ipExtractor,
		feedback:          feedback,
	}
}

func (s *GRPCServer) GetSpecs(ctx context.Context, _ *emptypb.Empty) (*SpecsItems, error) {
	items, err := s.catalogue.getSpecs(ctx, 0)
	if err != nil {
		return nil, err
	}

	return &SpecsItems{ //nolint:exhaustruct
		Items: items,
	}, nil
}

func (s *GRPCServer) GetPerspectives(
	ctx context.Context,
	_ *emptypb.Empty,
) (*PerspectivesItems, error) {
	items, err := s.catalogue.getPerspectives(ctx, nil)
	if err != nil {
		return nil, err
	}

	return &PerspectivesItems{ //nolint:exhaustruct
		Items: items,
	}, nil
}

func (s *GRPCServer) GetPerspectivePages(
	ctx context.Context,
	_ *emptypb.Empty,
) (*PerspectivePagesItems, error) {
	items, err := s.catalogue.getPerspectivePages(ctx)
	if err != nil {
		return nil, err
	}

	return &PerspectivePagesItems{ //nolint:exhaustruct
		Items: items,
	}, nil
}

func (s *GRPCServer) GetReCaptchaConfig(context.Context, *emptypb.Empty) (*ReCaptchaConfig, error) {
	return &ReCaptchaConfig{ //nolint:exhaustruct
		PublicKey: s.reCaptchaConfig.PublicKey,
	}, nil
}

func (s *GRPCServer) GetBrandIcons(context.Context, *emptypb.Empty) (*BrandIcons, error) {
	if len(s.fileStorageConfig.S3.Endpoint) == 0 {
		return nil, errNoEndpointProvided
	}

	parsedURL, err := url.Parse(s.fileStorageConfig.S3.Endpoint)
	if err != nil {
		return nil, err
	}

	parsedURL.Path = "/" + url.PathEscape(
		s.fileStorageConfig.Bucket,
	) + "/" + brandsSpriteImageFilename
	imageURL := parsedURL.String()

	parsedURL.Path = "/" + url.PathEscape(
		s.fileStorageConfig.Bucket,
	) + "/" + brandsSpriteCSSFilename
	cssURL := parsedURL.String()

	return &BrandIcons{ //nolint:exhaustruct
		Image: imageURL,
		Css:   cssURL,
	}, nil
}

func (s *GRPCServer) GetVehicleTypes(
	ctx context.Context,
	_ *emptypb.Empty,
) (*VehicleTypeItems, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
	}

	items, err := s.catalogue.getVehicleTypesTree(ctx, 0)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &VehicleTypeItems{
		Items: items,
	}, nil
}

func (s *GRPCServer) GetBrandVehicleTypes(
	ctx context.Context,
	in *GetBrandVehicleTypesRequest,
) (*BrandVehicleTypeItems, error) {
	items, err := s.catalogue.getBrandVehicleTypes(ctx, in.GetBrandId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &BrandVehicleTypeItems{
		Items: items,
	}, nil
}

func (s *GRPCServer) GetIP(ctx context.Context, in *APIGetIPRequest) (*APIIP, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	ip := net.ParseIP(in.GetIp())
	if ip == nil {
		return nil, status.Errorf(codes.InvalidArgument, "InvalidArgument")
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
	in *APICreateFeedbackRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	fv, err := s.feedback.Create(CreateFeedbackRequest{
		Name:    in.GetName(),
		Email:   in.GetEmail(),
		Message: in.GetMessage(),
		Captcha: in.GetCaptcha(),
		IP:      userCtx.IP.String(),
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if len(fv) > 0 {
		return nil, wrapFieldViolations(fv)
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
