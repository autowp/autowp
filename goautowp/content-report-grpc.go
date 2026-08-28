package goautowp

import (
	"context"
	"database/sql"

	"github.com/autowp/goautowp/contentreport"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/users"
	"github.com/autowp/goautowp/util"
	"github.com/autowp/goautowp/validation"
	"google.golang.org/genproto/googleapis/rpc/errdetails"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
	"google.golang.org/protobuf/types/known/timestamppb"
)

const contentReportMessageMaxLength = 2000

func reportEntityTypeFromGRPC(t ContentReportEntityType) (schema.ContentReportEntityType, bool) {
	switch t {
	case ContentReportEntityType_CONTENT_REPORT_ENTITY_TYPE_PICTURE:
		return schema.ContentReportEntityTypePicture, true
	case ContentReportEntityType_CONTENT_REPORT_ENTITY_TYPE_COMMENT:
		return schema.ContentReportEntityTypeComment, true
	case ContentReportEntityType_CONTENT_REPORT_ENTITY_TYPE_UNSPECIFIED:
		return 0, false
	default:
		return 0, false
	}
}

func reportReasonFromGRPC(r ContentReportReason) (schema.ContentReportReason, bool) {
	switch r {
	case ContentReportReason_CONTENT_REPORT_REASON_COPYRIGHT:
		return schema.ContentReportReasonCopyright, true
	case ContentReportReason_CONTENT_REPORT_REASON_ILLEGAL:
		return schema.ContentReportReasonIllegal, true
	case ContentReportReason_CONTENT_REPORT_REASON_SPAM:
		return schema.ContentReportReasonSpam, true
	case ContentReportReason_CONTENT_REPORT_REASON_PRIVACY:
		return schema.ContentReportReasonPrivacy, true
	case ContentReportReason_CONTENT_REPORT_REASON_OTHER:
		return schema.ContentReportReasonOther, true
	case ContentReportReason_CONTENT_REPORT_REASON_UNSPECIFIED:
		return 0, false
	default:
		return 0, false
	}
}

func (s *GRPCServer) CreateContentReport(
	ctx context.Context, in *CreateContentReportRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	entityType, ok := reportEntityTypeFromGRPC(in.GetEntityType())
	if !ok || in.GetEntityId() == 0 {
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
		return nil, wrapFieldViolations(fv)
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

func (s *GRPCServer) GetContentReports(
	ctx context.Context, in *ContentReportsRequest,
) (*ContentReportsResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	var statusFilter schema.ContentReportStatus

	switch in.GetStatus() {
	case ContentReportStatus_CONTENT_REPORT_STATUS_OPEN:
		statusFilter = schema.ContentReportStatusOpen
	case ContentReportStatus_CONTENT_REPORT_STATUS_ACCEPTED:
		statusFilter = schema.ContentReportStatusAccepted
	case ContentReportStatus_CONTENT_REPORT_STATUS_REJECTED:
		statusFilter = schema.ContentReportStatusRejected
	case ContentReportStatus_CONTENT_REPORT_STATUS_UNSPECIFIED:
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

	items := make([]*ContentReport, 0, len(rows))
	for _, row := range rows {
		items = append(items, contentReportRowToGRPC(row))
	}

	return &ContentReportsResponse{
		Items: items,
		Paginator: &Pages{
			PageCount:      pages.PageCount,
			Current:        pages.Current,
			TotalItemCount: pages.TotalItemCount,
		},
	}, nil
}

func (s *GRPCServer) ResolveContentReport(
	ctx context.Context, in *ResolveContentReportRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
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

func contentReportRowToGRPC(row schema.ContentReportRow) *ContentReport {
	out := &ContentReport{ //nolint:exhaustruct
		Id:         row.ID,
		EntityType: ContentReportEntityType(row.EntityType),
		EntityId:   row.EntityID,
		Reason:     ContentReportReason(row.Reason),
		Message:    row.Message,
		Status:     ContentReportStatus(row.Status),
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
