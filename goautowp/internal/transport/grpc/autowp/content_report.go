package autowp

import (
	"context"
	"database/sql"

	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/contentreport"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/transport/grpc/grpcerrors"
	"github.com/autowp/goautowp/internal/users"
	"github.com/autowp/goautowp/internal/util"
	"github.com/autowp/goautowp/internal/validation"
	"google.golang.org/genproto/googleapis/rpc/errdetails"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
	"google.golang.org/protobuf/types/known/timestamppb"
)

const contentReportMessageMaxLength = 2000

func reportEntityTypeFromGRPC(t pb.ContentReportEntityType) (schema.ContentReportEntityType, bool) {
	switch t {
	case pb.ContentReportEntityType_CONTENT_REPORT_ENTITY_TYPE_PICTURE:
		return schema.ContentReportEntityTypePicture, true
	case pb.ContentReportEntityType_CONTENT_REPORT_ENTITY_TYPE_COMMENT:
		return schema.ContentReportEntityTypeComment, true
	case pb.ContentReportEntityType_CONTENT_REPORT_ENTITY_TYPE_OTHER:
		return schema.ContentReportEntityTypeOther, true
	case pb.ContentReportEntityType_CONTENT_REPORT_ENTITY_TYPE_UNSPECIFIED:
		return 0, false
	default:
		return 0, false
	}
}

func reportReasonFromGRPC(r pb.ContentReportReason) (schema.ContentReportReason, bool) {
	switch r {
	case pb.ContentReportReason_CONTENT_REPORT_REASON_COPYRIGHT:
		return schema.ContentReportReasonCopyright, true
	case pb.ContentReportReason_CONTENT_REPORT_REASON_ILLEGAL:
		return schema.ContentReportReasonIllegal, true
	case pb.ContentReportReason_CONTENT_REPORT_REASON_SPAM:
		return schema.ContentReportReasonSpam, true
	case pb.ContentReportReason_CONTENT_REPORT_REASON_PRIVACY:
		return schema.ContentReportReasonPrivacy, true
	case pb.ContentReportReason_CONTENT_REPORT_REASON_OTHER:
		return schema.ContentReportReasonOther, true
	case pb.ContentReportReason_CONTENT_REPORT_REASON_UNSPECIFIED:
		return 0, false
	default:
		return 0, false
	}
}

func (s *Server) CreateContentReport(
	ctx context.Context, in *pb.CreateContentReportRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	entityType, ok := reportEntityTypeFromGRPC(in.GetEntityType())
	if !ok {
		return nil, status.Error(codes.InvalidArgument, "invalid entity")
	}

	if entityType != schema.ContentReportEntityTypeOther && in.GetEntityId() == 0 {
		return nil, status.Error(codes.InvalidArgument, "invalid entity")
	}

	reason, ok := reportReasonFromGRPC(in.GetReason())
	if !ok {
		return nil, status.Error(codes.InvalidArgument, "invalid reason")
	}

	messageFilter := validation.InputFilter{
		Filters: []validation.FilterInterface{&validation.StringTrimFilter{}},
		Validators: []validation.ValidatorInterface{
			&validation.NotEmpty{},
			&validation.StringLength{Min: 0, Max: contentReportMessageMaxLength},
		},
	}

	message, problems, err := messageFilter.IsValidString(in.GetMessage())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	fv := make([]*errdetails.BadRequest_FieldViolation, 0)
	for _, p := range problems {
		fv = append(fv, &errdetails.BadRequest_FieldViolation{Field: "message", Description: p})
	}

	if userCtx.UserID == 0 && s.captchaEnabled {
		captchaFilter := validation.InputFilter{
			Filters: []validation.FilterInterface{&validation.StringTrimFilter{}},
			Validators: []validation.ValidatorInterface{
				&validation.NotEmpty{},
				&validation.Recaptcha{ClientIP: userCtx.IP.String()},
			},
		}

		_, captchaProblems, captchaErr := captchaFilter.IsValidString(in.GetCaptcha())
		if captchaErr != nil {
			return nil, status.Error(codes.Internal, captchaErr.Error())
		}

		for _, p := range captchaProblems {
			fv = append(fv, &errdetails.BadRequest_FieldViolation{Field: "captcha", Description: p})
		}
	}

	if len(fv) > 0 {
		return nil, grpcerrors.FieldViolations(fv)
	}

	reporterID := sql.NullInt64{}
	if userCtx.UserID != 0 {
		reporterID = sql.NullInt64{Int64: userCtx.UserID, Valid: true}
	}

	reporterIP := ""
	if userCtx.IP != nil {
		reporterIP = userCtx.IP.String()
	}

	_, err = s.contentReports.Create(ctx, contentreport.CreateOptions{
		EntityType: entityType,
		EntityID:   in.GetEntityId(),
		Reason:     reason,
		Message:    message,
		ReporterID: reporterID,
		ReporterIP: reporterIP,
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) GetContentReports(
	ctx context.Context, in *pb.ContentReportsRequest,
) (*pb.ContentReportsResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	var statusFilter schema.ContentReportStatus

	switch in.GetStatus() {
	case pb.ContentReportStatus_CONTENT_REPORT_STATUS_OPEN:
		statusFilter = schema.ContentReportStatusOpen
	case pb.ContentReportStatus_CONTENT_REPORT_STATUS_ACCEPTED:
		statusFilter = schema.ContentReportStatusAccepted
	case pb.ContentReportStatus_CONTENT_REPORT_STATUS_REJECTED:
		statusFilter = schema.ContentReportStatusRejected
	case pb.ContentReportStatus_CONTENT_REPORT_STATUS_UNSPECIFIED:
	}

	entityTypeFilter, _ := reportEntityTypeFromGRPC(in.GetEntityType())

	rows, pages, err := s.contentReports.List(ctx, contentreport.ListOptions{
		Status:     statusFilter,
		EntityType: entityTypeFilter,
		Page:       in.GetPage(),
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	items := make([]*pb.ContentReport, 0, len(rows))
	for _, row := range rows {
		items = append(items, contentReportRowToGRPC(row))
	}

	return &pb.ContentReportsResponse{
		Items: items,
		Paginator: &pb.Pages{
			PageCount:      pages.PageCount,
			Current:        pages.Current,
			TotalItemCount: pages.TotalItemCount,
		},
	}, nil
}

func (s *Server) ResolveContentReport(
	ctx context.Context, in *pb.ResolveContentReportRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	if in.GetId() == 0 {
		return nil, status.Error(codes.InvalidArgument, "invalid id")
	}

	_, ok, err := s.contentReports.Resolve(
		ctx, in.GetId(), userCtx.UserID, in.GetAccepted(), in.GetResolution(),
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !ok {
		return nil, status.Error(codes.FailedPrecondition, "report not open")
	}

	return &emptypb.Empty{}, nil
}

func contentReportRowToGRPC(row schema.ContentReportRow) *pb.ContentReport {
	out := &pb.ContentReport{ //nolint:exhaustruct
		Id:         row.ID,
		EntityType: pb.ContentReportEntityType(row.EntityType),
		EntityId:   row.EntityID,
		Reason:     pb.ContentReportReason(row.Reason),
		Message:    row.Message,
		Status:     pb.ContentReportStatus(row.Status),
		CreateTime: timestamppb.New(row.CreatedAt),
		Resolution: row.Resolution,
	}

	if row.ReporterID.Valid {
		out.ReporterId = row.ReporterID.Int64
	}

	if row.ResolvedBy.Valid {
		out.ResolvedBy = row.ResolvedBy.Int64
	}

	if row.ResolvedAt.Valid {
		out.ResolveTime = timestamppb.New(row.ResolvedAt.Time)
	}

	return out
}
