package goautowp

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"strings"

	"github.com/autowp/goautowp/compliance"
	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/query"
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

const (
	gdprObjectionNameMaxLength      = 255
	gdprObjectionReferenceMaxLength = 255
	gdprObjectionNoteMaxLength      = 4000
)

// CreateGdprObjection, GetGdprObjections, DeleteGdprObjection, AcknowledgeGdprObjectionHit and
// GetGdprObjectionAffectedPictures manage the internal suppression list of people who objected to
// / requested erasure of their name being used as a public photo-author credit (GDPR Art. 17/21).
// All of it - viewing and mutating alike - is RoleModer: moderators are the ones who actually run
// into a suppressed name day to day (creating a person, linking an author, renaming one) and need
// to be able to act on it themselves rather than escalating every time.

func (s *GRPCServer) CreateGdprObjection(
	ctx context.Context, in *CreateGdprObjectionRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, s.auth.GRPCError(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	fv := make([]*errdetails.BadRequest_FieldViolation, 0)

	names := make([]string, 0, len(in.GetNames()))

	nameFilter := validation.InputFilter{
		Filters: []validation.FilterInterface{&validation.StringTrimFilter{}},
		Validators: []validation.ValidatorInterface{
			&validation.StringLength{Min: 0, Max: gdprObjectionNameMaxLength},
		},
	}

	for _, rawName := range in.GetNames() {
		name, problems, filterErr := nameFilter.IsValidString(rawName)
		if filterErr != nil {
			return nil, status.Error(codes.Internal, filterErr.Error())
		}

		for _, p := range problems {
			fv = append(fv, &errdetails.BadRequest_FieldViolation{Field: "names", Description: p})
		}

		if name != "" {
			names = append(names, name)
		}
	}

	if len(names) == 0 {
		fv = append(
			fv,
			&errdetails.BadRequest_FieldViolation{Field: "names", Description: "at least one name is required"},
		)
	}

	referenceFilter := validation.InputFilter{
		Filters: []validation.FilterInterface{&validation.StringTrimFilter{}},
		Validators: []validation.ValidatorInterface{
			&validation.NotEmpty{},
			&validation.StringLength{Min: 0, Max: gdprObjectionReferenceMaxLength},
		},
	}

	reference, problems, err := referenceFilter.IsValidString(in.GetReference())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	for _, p := range problems {
		fv = append(fv, &errdetails.BadRequest_FieldViolation{Field: "reference", Description: p})
	}

	noteFilter := validation.InputFilter{
		Filters: []validation.FilterInterface{&validation.StringTrimFilter{}},
		Validators: []validation.ValidatorInterface{
			&validation.StringLength{Min: 0, Max: gdprObjectionNoteMaxLength},
		},
	}

	note, problems, err := noteFilter.IsValidString(in.GetNote())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	for _, p := range problems {
		fv = append(fv, &errdetails.BadRequest_FieldViolation{Field: "note", Description: p})
	}

	if len(fv) > 0 {
		return nil, wrapFieldViolations(fv)
	}

	_, err = s.complianceRepository.Create(ctx, compliance.CreateOptions{
		Names:        names,
		Reference:    reference,
		ContactEmail: in.GetContactEmail(),
		Note:         note,
		SourceText:   in.GetSourceText(),
		AuthorUserID: userCtx.UserID,
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.events.Add(ctx, Event{
		UserID: userCtx.UserID,
		Message: fmt.Sprintf(
			"GDPR: %q добавлено в suppression list (реф. %q)", strings.Join(names, " / "), reference,
		),
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *GRPCServer) GetGdprObjections(
	ctx context.Context, in *GetGdprObjectionsRequest,
) (*GdprObjectionsResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, s.auth.GRPCError(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	rows, pages, err := s.complianceRepository.List(ctx, compliance.ListOptions{
		Page:     in.GetPage(),
		HitsOnly: in.GetHitsOnly(),
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	items := make([]*GdprObjection, 0, len(rows))

	for _, row := range rows {
		names, namesErr := s.complianceRepository.Names(ctx, row.ID)
		if namesErr != nil {
			return nil, status.Error(codes.Internal, namesErr.Error())
		}

		items = append(items, gdprObjectionRowToGRPC(row, names))
	}

	return &GdprObjectionsResponse{
		Items: items,
		Paginator: &Pages{
			PageCount:      pages.PageCount,
			Current:        pages.Current,
			TotalItemCount: pages.TotalItemCount,
		},
	}, nil
}

func (s *GRPCServer) DeleteGdprObjection(
	ctx context.Context, in *DeleteGdprObjectionRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, s.auth.GRPCError(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	if in.GetId() == 0 {
		return nil, status.Error(codes.InvalidArgument, "invalid id")
	}

	row, found, err := s.complianceRepository.Get(ctx, in.GetId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !found {
		return nil, status.Error(codes.NotFound, "not found")
	}

	names, err := s.complianceRepository.Names(ctx, in.GetId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	ok, err := s.complianceRepository.Delete(ctx, in.GetId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !ok {
		return nil, status.Error(codes.NotFound, "not found")
	}

	err = s.events.Add(ctx, Event{
		UserID: userCtx.UserID,
		Message: fmt.Sprintf(
			"GDPR: %q удалено из suppression list (реф. %q)", strings.Join(names, " / "), row.Reference,
		),
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *GRPCServer) AcknowledgeGdprObjectionHit(
	ctx context.Context, in *AcknowledgeGdprObjectionHitRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, s.auth.GRPCError(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	if in.GetId() == 0 {
		return nil, status.Error(codes.InvalidArgument, "invalid id")
	}

	row, found, err := s.complianceRepository.Get(ctx, in.GetId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !found {
		return nil, status.Error(codes.NotFound, "not found")
	}

	names, err := s.complianceRepository.Names(ctx, in.GetId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if err = s.complianceRepository.Acknowledge(ctx, in.GetId()); err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.events.Add(ctx, Event{
		UserID: userCtx.UserID,
		Message: fmt.Sprintf(
			"GDPR: повторное срабатывание по %q (suppression list, реф. %q) отмечено как рассмотренное",
			strings.Join(names, " / "), row.Reference,
		),
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *GRPCServer) GetGdprObjectionSourceText(
	ctx context.Context, in *GetGdprObjectionSourceTextRequest,
) (*GdprObjectionSourceText, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, s.auth.GRPCError(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	if in.GetId() == 0 {
		return nil, status.Error(codes.InvalidArgument, "invalid id")
	}

	row, found, err := s.complianceRepository.Get(ctx, in.GetId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !found {
		return nil, status.Error(codes.NotFound, "not found")
	}

	text, err := s.complianceRepository.SourceText(ctx, row)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &GdprObjectionSourceText{Text: text}, nil
}

func (s *GRPCServer) GetGdprObjectionAffectedPictures(
	ctx context.Context, in *GetGdprObjectionAffectedPicturesRequest,
) (*GetGdprObjectionAffectedPicturesResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, s.auth.GRPCError(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	if in.GetId() == 0 {
		return nil, status.Error(codes.InvalidArgument, "invalid id")
	}

	pictureIDs, pictureIdentities, err := s.picturesRepository.PicturesSuppressedByObjection(ctx, in.GetId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &GetGdprObjectionAffectedPicturesResponse{
		PictureIds:        pictureIDs,
		PictureIdentities: pictureIdentities,
	}, nil
}

func (s *GRPCServer) GetGdprObjectionCleanupCandidates(
	ctx context.Context, in *GetGdprObjectionCleanupCandidatesRequest,
) (*GetGdprObjectionCleanupCandidatesResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, s.auth.GRPCError(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	if in.GetObjectionId() == 0 {
		return nil, status.Error(codes.InvalidArgument, "invalid objection_id")
	}

	rows, err := s.complianceRepository.CleanupCandidates(ctx, in.GetObjectionId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	items := make([]*GdprObjectionCleanupCandidate, 0, len(rows))
	for _, row := range rows {
		items = append(items, cleanupCandidateRowToGRPC(row))
	}

	return &GetGdprObjectionCleanupCandidatesResponse{Items: items}, nil
}

func (s *GRPCServer) ResolveGdprObjectionCleanupCandidate(
	ctx context.Context, in *ResolveGdprObjectionCleanupCandidateRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, s.auth.GRPCError(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	if in.GetId() == 0 {
		return nil, status.Error(codes.InvalidArgument, "invalid id")
	}

	ok, err := s.complianceRepository.ResolveCleanupCandidate(ctx, in.GetId(), userCtx.UserID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !ok {
		return nil, status.Error(codes.NotFound, "not found")
	}

	return &emptypb.Empty{}, nil
}

// AddGdprObjectionCleanupCandidate manually attaches a picture to a case's cleanup-candidate
// queue - for content the SuppressAuthor-time site-wide search (FindPicturesWithCopyrightsTextContaining)
// missed, e.g. a spelling variant of the name, that a moderator has since found by hand (searching
// the inbox, say) and wants tracked against this case alongside the automatically found hits.
// Reuses AddCleanupCandidates, so re-adding an already-recorded picture is a no-op.
func (s *GRPCServer) AddGdprObjectionCleanupCandidate(
	ctx context.Context, in *AddGdprObjectionCleanupCandidateRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, s.auth.GRPCError(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	if in.GetObjectionId() == 0 {
		return nil, status.Error(codes.InvalidArgument, "invalid objection_id")
	}

	if in.GetPictureId() == 0 {
		return nil, status.Error(codes.InvalidArgument, "invalid picture_id")
	}

	row, found, err := s.complianceRepository.Get(ctx, in.GetObjectionId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !found {
		return nil, status.Error(codes.NotFound, "objection not found")
	}

	if _, err = s.picturesRepository.Picture(
		ctx, &query.PictureListOptions{ID: in.GetPictureId()}, nil, pictures.OrderByNone,
	); err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, status.Error(codes.NotFound, "picture not found")
		}

		return nil, status.Error(codes.Internal, err.Error())
	}

	if err = s.complianceRepository.AddCleanupCandidates(
		ctx,
		in.GetObjectionId(),
		schema.GdprObjectionCleanupCandidateEntityTypeCopyrightsTextPicture,
		[]int64{in.GetPictureId()},
	); err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	names, err := s.complianceRepository.Names(ctx, in.GetObjectionId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.events.Add(ctx, Event{
		UserID: userCtx.UserID,
		Message: fmt.Sprintf(
			"GDPR: фото #%d вручную добавлено в cleanup candidates по %q (suppression list, реф. %q)",
			in.GetPictureId(), strings.Join(names, " / "), row.Reference,
		),
		Pictures: []int64{in.GetPictureId()},
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func cleanupCandidateEntityTypeToGRPC(
	t schema.GdprObjectionCleanupCandidateEntityType,
) GdprObjectionCleanupCandidateEntityType {
	switch t {
	case schema.GdprObjectionCleanupCandidateEntityTypeCopyrightsTextPicture:
		return GdprObjectionCleanupCandidateEntityType_GDPR_OBJECTION_CLEANUP_CANDIDATE_ENTITY_TYPE_COPYRIGHTS_TEXT_PICTURE
	case schema.GdprObjectionCleanupCandidateEntityTypeComment:
		return GdprObjectionCleanupCandidateEntityType_GDPR_OBJECTION_CLEANUP_CANDIDATE_ENTITY_TYPE_COMMENT
	default:
		return GdprObjectionCleanupCandidateEntityType_GDPR_OBJECTION_CLEANUP_CANDIDATE_ENTITY_TYPE_UNSPECIFIED
	}
}

func cleanupCandidateRowToGRPC(row schema.GdprObjectionCleanupCandidateRow) *GdprObjectionCleanupCandidate {
	out := &GdprObjectionCleanupCandidate{ //nolint:exhaustruct
		Id:         row.ID,
		EntityType: cleanupCandidateEntityTypeToGRPC(row.EntityType),
		EntityId:   row.EntityID,
		FoundTime:  timestamppb.New(row.FoundAt),
	}

	if row.ResolvedAt.Valid {
		out.ResolveTime = timestamppb.New(row.ResolvedAt.Time)
	}

	return out
}

func gdprObjectionRowToGRPC(row schema.GdprObjectionRow, names []string) *GdprObjection {
	out := &GdprObjection{ //nolint:exhaustruct
		Id:            row.ID,
		Names:         names,
		Reference:     row.Reference,
		ContactEmail:  row.ContactEmail,
		Note:          row.Note,
		CreateTime:    timestamppb.New(row.CreatedAt),
		HitCount:      int64(row.HitCount),
		HasSourceText: row.SourceTextID.Valid,
	}

	if row.LastHitAt.Valid {
		out.LastHitTime = timestamppb.New(row.LastHitAt.Time)
	}

	return out
}
